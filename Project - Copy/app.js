const burger = document.getElementById("burger");
const sidebar = document.getElementById("sidebar");
const profileIcon = document.getElementById("profileIcon");
const profileMenu = document.getElementById("profileMenu");
const closeProfile = document.getElementById("closeProfile");

let loggedIn = false; // simulated auth

//SIDEBAR (BURGER MENU) down below

// Toggle sidebar when burger clicked
if (burger) {
    burger.addEventListener("click", (e) => {
        e.stopPropagation(); // prevent document click
        sidebar.classList.toggle("show");
    });
}

// Prevent clicks inside sidebar from closing it
if (sidebar) {
    sidebar.addEventListener("click", (e) => {
        e.stopPropagation();
    });
}

// PROFILE MENU down below

// Toggle profile menu
profileIcon.addEventListener("click", (e) => {
    e.stopPropagation();
    profileMenu.style.display =
        profileMenu.style.display === "block" ? "none" : "block";
});

// Close profile menu via X
closeProfile.addEventListener("click", (e) => {
    e.stopPropagation();
    profileMenu.style.display = "none";
});

// GLOBAL CLICK HANDLER down below

// Click anywhere else closes BOTH menus
document.addEventListener("click", () => {
    // Close profile menu
    profileMenu.style.display = "none";

    // Close sidebar (mobile only)
    if (sidebar.classList.contains("show")) {
        sidebar.classList.remove("show");
    }
});

// AUTH GUARD for future php to be impelemented buy you guys i.e the backend guys


function requireLogin() {
    if (!loggedIn) {
        alert("Please log in to continue.");
    }
}
