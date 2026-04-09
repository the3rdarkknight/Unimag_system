const closureDate = new Date("2026-04-30");
const today = new Date();

if(today > closureDate){

const banner = document.querySelector(".info-banner");

banner.innerHTML =
"Submissions are now closed. You can still edit existing contributions until the final closure date.";

}
