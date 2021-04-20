async function fetchAs(fmt, ...arg) {
  const response = await fetch(...arg);
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }
  return response[fmt].apply(response);
}

(async () => {

document.querySelectorAll("form").forEach(
  ($form) => $form.addEventListener("submit", (evt) => evt.preventDefault()),
);

const supportedHardwareTypes = await fetchAs("json", "hardware-types.json");
const $hardware = document.querySelector("#testbed-hardware");
for (const key of Object.keys(supportedHardwareTypes)) {
  const $option = document.createElement("option");
  $option.textContent = key;
  $hardware.append($option);
}
const getSelectedHardware = () => {
  const $option = $hardware.selectedOptions[0];
  let ht;
  if (!$option || !(ht = supportedHardwareTypes[$option.value])) {
    return [];
  }
  return [$option.value, ht];
}

const $hardwareAvail = document.querySelector("#hardware-avail");
$hardware.addEventListener("change", async () => {
  const [, ht] = getSelectedHardware();
  if (!ht) {
    $hardwareAvail.textContent = "";
    return;
  }
  $hardwareAvail.textContent = `loading ${ht.testbed} free resources`;
  try {
    const advertisement = await fetchAs("json", `advertisement/${ht.testbed}.json`);
    $hardwareAvail.textContent = `${advertisement.availHardware[ht.type]} free ${ht.type} at ${ht.testbed}`;
  } catch (err) {
    $hardwareAvail.textContent = `free resources unknown ${err}`;
  }
});

const $nodeCount = document.querySelector("#node-count");
const $requestRSpec = document.querySelector("#request-rspec");
const updateRequestRSpec = () => {
  const [testbedHardware] = getSelectedHardware();
  if (!testbedHardware) {
    $requestRSpec.value = "";
  }
  const nNodes = Number.parseInt($nodeCount.value, 10);
  $requestRSpec.value = `${new URL(`request/${testbedHardware}-${nNodes}.rspec`, location.href)}`;
};
$nodeCount.addEventListener("change", updateRequestRSpec);
$hardware.addEventListener("change", updateRequestRSpec);
$requestRSpec.addEventListener("focus", () => $requestRSpec.select());

const $setupScriptForm = document.querySelector("#setup-script-form");
const $manifestFile = document.querySelector("#manifest-file");
const $setupAction = document.querySelector("#setup-action");
const $setupScript = document.querySelector("#setup-script");
$setupScriptForm.addEventListener("submit", async (evt) => {
  evt.preventDefault();
  $setupScript.value = "";
  const file = $manifestFile.files[0];
  if (!file) {
    return;
  }

  try {
    const manifest = await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.addEventListener("load", () => resolve(reader.result));
      reader.addEventListener("error", () => reject(reader.error));
      reader.readAsText(file);
    });

    const formData = new FormData();
    formData.set("manifest", manifest);
    formData.set("actions", (() => {
      const actions = [];
      for (const $option of $setupAction.selectedOptions) {
        actions.push($option.value);
      }
      return actions.join();
    })());

    $setupScript.value = await fetchAs("text", "setup", {
      method: "POST",
      body: formData,
    });
  } catch (err) {
    $setupScript.value = `# ${err}`;
  }
});
$setupScript.addEventListener("focus", () => $setupScript.select());
document.querySelector("#setup-action-all").addEventListener("click", (evt) => {
  evt.preventDefault();
  for (const $option of $setupAction.options) {
    $option.selected = true;
  }
});

})().catch(console.error);
