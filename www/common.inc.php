<?php
date_default_timezone_set("UTC");
define("TMPDIR", sys_get_temp_dir()."/NDNrspec");
@mkdir(TMPDIR, 0777, TRUE);

define("NS_RSPEC", "http://www.geni.net/resources/rspec/3");
define("NS_JFED", "http://jfed.iminds.be/rspec/ext/jfed/1");

function xpath(SimpleXMLELement $element, string $query): array {
  $element->registerXPathNamespace("r", NS_RSPEC);
  return $element->xpath($query);
}

$supportedHardwareTypes = [];
foreach ([4, 5] as $p) {
  $supportedHardwareTypes[sprintf("vwall1-pcgen02p%d", $p)] = (object)[
    "testbed"=>"vwall1",
    "type"=>sprintf("pcgen02-%dp", $p),
    "componentManager"=>"urn:publicid:IDN+wall1.ilabt.iminds.be+authority+cm",
    "diskImage"=>"urn:publicid:IDN+wall1.ilabt.iminds.be+image+emulab-ops:UBUNTU20-64-STD",
    "ndndpdkDockerTag"=>"nehalem-threadsleep", // nehalem
  ];
}
foreach ([1, 2, 4, 7] as $p) {
  $supportedHardwareTypes[sprintf("vwall2-pcgen03p%d", $p)] = (object)[
    "testbed"=>"vwall2",
    "type"=>sprintf("pcgen03-%dp", $p),
    "componentManager"=>"urn:publicid:IDN+wall2.ilabt.iminds.be+authority+cm",
    "diskImage"=>"urn:publicid:IDN+wall2.ilabt.iminds.be+image+emulab-ops:UBUNTU20-64-STD",
    "ndndpdkDockerTag"=>"nehalem-threadsleep", // westmere
  ];
}
$supportedHardwareTypes["grid5000-paravance"] = (object)[
  "testbed"=>"grid5000",
  "type"=>"paravance-rennes",
  "componentManager"=>"urn:publicid:IDN+am.grid5000.fr+authority+am",
  "diskImage"=>"urn:publicid:IDN+am.grid5000.fr+image+kadeploy3:ubuntu2004-x64-min",
  "ndndpdkDockerTag"=>"nehalem-threadsleep", // haswell
];
$supportedHardwareTypes["grid5000-gros"] = (object)[
  "testbed"=>"grid5000",
  "type"=>"gros-nancy",
  "componentManager"=>"urn:publicid:IDN+am.grid5000.fr+authority+am",
  "diskImage"=>"urn:publicid:IDN+am.grid5000.fr+image+kadeploy3:ubuntu2004-x64-min",
  "ndndpdkDockerTag"=>"nehalem-threadsleep", // cascadelake
];
$supportedHardwareTypes["emulab-d710"] = (object)[
  "testbed"=>"emulab",
  "type"=>"d710",
  "componentManager"=>"urn:publicid:IDN+emulab.net+authority+cm",
  "diskImage"=>"urn:publicid:IDN+emulab.net+image+emulab-ops:UBUNTU20-64-STD",
  "ndndpdkDockerTag"=>"nehalem-threadsleep", // nehalem
];
$supportedHardwareTypes["cloudlabUtah-xl170"] = (object)[
  "testbed"=>"cloudlabUtah",
  "type"=>"xl170",
  "componentManager"=>"urn:publicid:IDN+utah.cloudlab.us+authority+cm",
  "diskImage"=>"urn:publicid:IDN+utah.cloudlab.us+image+emulab-ops:UBUNTU20-64-STD",
  "ndndpdkDockerTag"=>"nehalem-threadsleep", // broadwell
];
?>
