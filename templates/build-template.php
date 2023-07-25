<?php
// Echos out the template preview, or returns false if the preview isn't available yet
$getTemplate = idemailwiz_build_template();
if (!$getTemplate) {
    echo 'Loading template....<br/><em>If you see this for more than a few seconds, something has gone wrong.</em>;';
}
 ?>