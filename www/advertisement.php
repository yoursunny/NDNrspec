<?php
$testbed = basename($uriComps[2], ".json");
if (!preg_match("/^[0-9a-z]+$/i", $testbed)) {
  http_response_code(404);
  die;
}
$cacheFile = sprintf("%s/%s.json", TMPDIR, $testbed);
$updated = @filemtime($cacheFile);
if ($updated !== FALSE && $updated + 150 > time()) {
  header("Content-Type: application/json");
  readfile($cacheFile);
  die;
}

$flsmonUri = sprintf("https://flsmonitor-api.fed4fire.eu/result?testbed=%s&testdefinitionname=listResources&last=1", $testbed);
$flsmonResult = json_decode(file_get_contents($flsmonUri));
if (!is_array($flsmonResult) || count($flsmonResult) < 1 || $flsmonResult[0]->summary !== "SUCCESS") {
  http_response_code(500);
  die;
}

$rspec = simplexml_load_file($flsmonResult[0]->results->rspecUrl);

$availHardware = array();
foreach (xpath($rspec, "r:node[@exclusive='true'][r:available[@now='true']][r:sliver_type[@name='raw-pc']]") as $node) {
  foreach (xpath($node, "r:hardware_type") as $hardwareType) {
    $hwType = strval($hardwareType["name"]);
    $availHardware[$hwType] = 1 + @$availHardware[$hwType];
  }
}
ksort($availHardware);

$json = json_encode(array(
  "testbed"=>$testbed,
  "updated"=>$flsmonResult[0]->created,
  "rspec"=>$flsmonResult[0]->results->rspecUrl,
  "availHardware"=>$availHardware,
), JSON_PRETTY_PRINT);
file_put_contents($cacheFile, $json);

header("Content-Type: application/json");
header("Cache-Control: max-age=60");
echo $json;
?>
