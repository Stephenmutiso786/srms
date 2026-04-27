<?php
/**
 * Cache layer for SRMS - supports file-based cache and Redis
 * Reduces database load and improves response times
 */

class AppCache {
	private static $instance = null;
	private $driver = 'file';
	private $ttl = 3600;
	private $cacheDir = '';
	private $prefix = 'srms_';
	private $redis = null;

	private function __construct() {
		$this->cacheDir = __DIR__ . '/../../cache';
		if (!is_dir($this->cacheDir)) {
			@mkdir($this->cacheDir, 0755, true);
		}

		// Try Redis first if available
		if (extension_loaded('redis')) {
			try {
				$this->redis = new Redis();
				$redisHost = getenv('REDIS_HOST') ?: 'localhost';
				$redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
				if ($this->redis->connect($redisHost, $redisPort, 2)) {
					$this->driver = 'redis';
					return;
				}
			} catch (Throwable $e) {
				$this->redis = null;
			}
		}

		// Fall back to file cache
		$this->driver = 'file';
	}

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get(string $key): ?string {
		if ($this->driver === 'redis' && $this->redis) {
			return $this->redis->get($this->prefix . $key);
		}
		return $this->getFile($key);
	}

	public function set(string $key, string $value, int $ttl = null): bool {
		$ttl = $ttl ?? $this->ttl;

		if ($this->driver === 'redis' && $this->redis) {
			return $this->redis->setex($this->prefix . $key, $ttl, $value);
		}

		return $this->setFile($key, $value, $ttl);
	}

	public function delete(string $key): bool {
		if ($this->driver === 'redis' && $this->redis) {
			return $this->redis->del($this->prefix . $key) > 0;
		}
		return $this->deleteFile($key);
	}

	public function flush(): bool {
		if ($this->driver === 'redis' && $this->redis) {
			return $this->redis->flushDb();
		}
		return $this->flushFiles();
	}

	// File-based cache methods
	private function getCacheFilePath(string $key): string {
		$safeKey = preg_replace('/[^a-z0-9_\-]/i', '_', $key);
		return $this->cacheDir . '/' . $safeKey . '.cache';
	}

	private function getFile(string $key): ?string {
		$path = $this->getCacheFilePath($key);
		if (!is_file($path)) {
			return null;
		}

		$data = @file_get_contents($path);
		if (!$data) {
			return null;
		}

		list($expiry, $value) = @unserialize($data);
		if ($expiry && $expiry < time()) {
			@unlink($path);
			return null;
		}

		return $value;
	}

	private function setFile(string $key, string $value, int $ttl): bool {
		$path = $this->getCacheFilePath($key);
		$expiry = time() + $ttl;
		$data = serialize([$expiry, $value]);
		return (bool)@file_put_contents($path, $data, LOCK_EX);
	}

	private function deleteFile(string $key): bool {
		$path = $this->getCacheFilePath($key);
		return @unlink($path);
	}

	private function flushFiles(): bool {
		$files = @glob($this->cacheDir . '/*.cache');
		foreach ($files ?: [] as $file) {
			@unlink($file);
		}
		return true;
	}

	public function getDriver(): string {
		return $this->driver;
	}
}

// Convenient global functions
function app_cache_get(string $key): ?string {
	return AppCache::getInstance()->get($key);
}

function app_cache_set(string $key, string $value, int $ttl = 3600): bool {
	return AppCache::getInstance()->set($key, $value, $ttl);
}

function app_cache_delete(string $key): bool {
	return AppCache::getInstance()->delete($key);
}

function app_cache_flush(): bool {
	return AppCache::getInstance()->flush();
}

function app_cache_json_get(string $key): ?array {
	$cached = app_cache_get($key);
	if ($cached === null) {
		return null;
	}
	return json_decode($cached, true);
}

function app_cache_json_set(string $key, array $data, int $ttl = 3600): bool {
	return app_cache_set($key, json_encode($data), $ttl);
}
