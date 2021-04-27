<?php
$rspec = simplexml_load_string($_POST["manifest"]);
$componentManager = "";
$hwType = "";

$nodes = array();
foreach (xpath($rspec, "r:node") as $node) {
  $id = strval($node["client_id"]);
  $componentManager = strval($node["component_manager_id"]);
  foreach (xpath($node, "r:hardware_type") as $hardwareType) {
    $hwType = strval($hardwareType["name"]);
  }

  $mac = "";
  foreach (xpath($node, "r:interface_ref | r:interface") as $interfaceRef) {
    $mac = strtolower(str_replace(":", "", strval($interfaceRef["mac_address"])));
  }
  if (!preg_match("/^[0-9a-f]{12}$/", $mac)) {
    continue;
  }
  $mac = substr($mac, 0, 2).":".substr($mac, 2, 2).":".substr($mac, 4, 2).":".substr($mac, 6, 2).":".substr($mac, 8, 2).":".substr($mac, 10, 2);

  $nodes[] = array(
    "id"=>$id,
    "ifname"=>"f".substr($id, 1),
    "mac"=>$mac,
  );
}

$ndndpdkDockerTag = "nehalem";
foreach ($supportedHardwareTypes as $ht) {
  if ($componentManager === $ht->componentManager && $hwType === $ht->type) {
    $ndndpdkDockerTag = $ht->ndndpdkDockerTag;
  }
}

header("Content-Type: text/plain");
readfile("setup.sh");
printf("\nJ=%s\n", escapeshellarg(json_encode(array(
  "ndndpdkDockerImage"=>"docker.yoursunny.dev/ndn-dpdk:".$ndndpdkDockerTag,
  "nodes"=>$nodes,
))));

if (is_array($_POST["a"])) {
  foreach ($_POST["a"] as $a) {
    printf("%s\n", escapeshellcmd($a));
  }
}
?>
