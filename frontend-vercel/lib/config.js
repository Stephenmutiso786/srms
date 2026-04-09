export const appConfig = {
  name: process.env.NEXT_PUBLIC_APP_NAME || "Elimu Hub",
  backendUrl: process.env.NEXT_PUBLIC_BACKEND_URL || "https://srms-n7g2.onrender.com",
  apiBaseUrl: process.env.NEXT_PUBLIC_API_BASE_URL || "https://srms-n7g2.onrender.com/api"
};

export function buildLegacyUrl(path = "") {
  const cleanPath = path.startsWith("/") ? path.slice(1) : path;
  return `${appConfig.backendUrl}/${cleanPath}`;
}

export function buildApiUrl(path = "") {
  const cleanPath = path.startsWith("/") ? path.slice(1) : path;
  return `${appConfig.apiBaseUrl}/${cleanPath}`;
}
