<?php
list($testbed, $hwType, $nNodes) = explode("-", basename($uriComps[2], ".rspec"));

$ht = (object)$supportedHardwareTypes[$testbed."-".$hwType];
if ($ht === NULL) {
  http_response_code(404);
  die;
}

$nNodes = intval($nNodes);
if ($nNodes < 2 || $nNodes > 250) {
  http_response_code(404);
  die;
}
$nodeRadians = 2 * pi() / $nNodes;

$rspec = new SimpleXMLElement("<rspec/>");
$rspec["xmlns"] = NS_RSPEC;
$rspec["type"] = "request";
$rspec["generated_by"] = "yoursunny/ndn-fed4fire";
$rspec["generated"] = date("c");

for ($i = 0; $i < $nNodes; ++$i) {
  $node = $rspec->addChild("node");
  $node["client_id"] = "n".$i;
  $node["exclusive"] = "true";
  $node["component_manager_id"] = $ht->componentManager;

  $location = $node->addChild("location");
  $location["xmlns"] = NS_JFED;
  $location["x"] = 200 + 100 * sin($i * $nodeRadians);
  $location["y"] = 150 - 100 * cos($i * $nodeRadians);

  $sliverType = $node->addChild("sliver_type");
  $sliverType["name"] = "raw-pc";
  $sliverType->addChild("disk_image")["name"] = $ht->diskImage;
  $node->addChild("hardware_type")["name"] = $ht->type;

  $interface = $node->addChild("interface");
  $interfaceName = "f".$i;
  $interface["client_id"] = $interfaceName;
  $ip = $interface->addChild("ip");
  $ip["address"] = long2ip(0x0A960001 + $i); // 10.150.0.(1+$i)
  $ip["netmask"] = "255.255.255.0";
  $ip["type"] = "ipv4";
}

$link = $rspec->addChild("link");
$link["client_id"] = "linkL";
$link->addChild("component_manager")["name"] = $ht->componentManager;
for ($i = 0; $i < $nNodes; ++$i) {
  $link->addChild("interface_ref")["client_id"] = "f".$i;
}
$link->addChild("link_type")["name"] = "lan";

header("Content-Type: text/xml");
echo $rspec->asXML();
?>
