<?php

$originalString = "Kyaw Zin Thant";

// Encode the string to Base64
$base64EncodedString = base64_encode($originalString);

echo "Original String: " . $originalString . "\n";
echo "Base64 Encoded: " . $base64EncodedString . "\n";
