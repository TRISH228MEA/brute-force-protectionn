// BRUTE FORCE PROTECTION
// Frontend starter script

document.addEventListener("DOMContentLoaded", () => {
    console.log("Brute Force Protection website loaded!");

    const loginForm = document.getElementById("loginForm");

    if (loginForm) {
        loginForm.addEventListener("submit", (event) => {
            event.preventDefault();
            alert("Login page is ready. Supabase authentication will be connected next.");
        });
    }
});
