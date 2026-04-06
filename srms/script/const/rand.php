<?php
function GRS($length)
{
$stringSpace = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$pieces = [];
$max = mb_strlen($stringSpace, '8bit') - 1;
for ($i = 0; $i < $length; ++ $i) {
$pieces[] = $stringSpace[random_int(0, $max)];
}
return implode('', $pieces);
}

function GP($length)
{
$stringSpace = '#@!0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$pieces = [];
$max = mb_strlen($stringSpace, '8bit') - 1;
for ($i = 0; $i < $length; ++ $i) {
$pieces[] = $stringSpace[random_int(0, $max)];
}
return implode('', $pieces);
}
?>
