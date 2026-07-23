<?php
/*
 * wp-decode-payload.php — Statically decode a fake-image malware payload. PRINTS ONLY.
 *
 * D-Solutions WP fleet security tooling.
 *
 * The "fake-plugin eval loader" family hides its real payload in a file with a
 * fake image extension (.png/.gif): 3 junk magic bytes, then a base64 blob run
 * through a strtr() substitution cipher keyed by two 64-char alphabets.
 *
 * This decodes it OFFLINE and echoes the result. It NEVER eval()s or executes
 * anything — safe to run on a suspect file for investigation.
 *
 * Usage:
 *   php wp-decode-payload.php <file.png> [key1] [key2]
 *
 * The two keys are the loader's strtr() tables. Extract them per-variant: open
 * the plugin's .txt loader, find the two functions that each return a 64-char
 * string, and pass them as key1/key2. The built-in defaults match one common
 * variant but WILL differ across samples.
 */
function k1() { return "c0nXsW37tRSxuLKBmwAoJ/gOjdQ8=DG1F52C9iNHYUek6rafZzyEbv+pq4IlTVMhP"; }
function k2() { return "H=vQ5UV1NWxmOEJu3SfTX2pBeyCd6aR8F4wAsKtoGcZjzIiY+0nLkb/Dq7glh9MrP"; }

$f = $argv[1] ?? '';
$a = $argv[2] ?? k1();
$b = $argv[3] ?? k2();
if (!$f || !file_exists($f)) {
    fwrite(STDERR, "Usage: php wp-decode-payload.php <file.png> [key1] [key2]\n");
    exit(1);
}
if (strlen($a) !== strlen($b)) {
    fwrite(STDERR, "key1 and key2 must be the same length\n");
    exit(1);
}

$data = substr(@file_get_contents($f), 3);   // drop 3 fake-magic bytes
$map = array();
for ($i = 0; $i < strlen($a); $i++) { $map[$a[$i]] = $b[$i]; }
$dec = base64_decode(strtr($data, $map));

echo "=== DECODED (len=" . strlen($dec) . ") ===\n";
echo $dec . "\n";
echo "=== END ===\n";

// Extract IOCs from the decoded output, e.g.:
//   php wp-decode-payload.php x.png | grep -oE 'https?://[a-zA-Z0-9./_-]+'
//   php wp-decode-payload.php x.png | grep -oiE '\$_COOKIE|\$_POST|file_put_contents|update_option|system|shell_exec'
