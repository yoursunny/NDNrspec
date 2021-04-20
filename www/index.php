<?php
require_once "common.inc.php";

$uriComps = explode("/", $_SERVER["REQUEST_URI"]);
switch ($uriComps[1]) {
  case "robots.txt":
    header("Content-Type: text/plain");
    printf("User-Agent: *\nDisallow: /\n");
    break;
  case "favicon.ico":
    header("Location: https://www.fed4fire.eu/favicon.ico");
    break;
  case "":
    readfile("app.html");
    break;
  case "hardware-types.json":
    header("Content-Type: application/json");
    echo json_encode($supportedHardwareTypes);
    break;
  case "advertisement":
    include "advertisement.php";
    break;
  case "request":
    include "request.php";
    break;
  case "setup":
    include "setup.php";
    break;
  default:
    http_response_code(404);
    break;
}
?>
