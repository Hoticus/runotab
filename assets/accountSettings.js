const select_avatar = document.getElementById("select_avatar_photo_form_photo");
const select_avatar_label = document.getElementById(
    "select_avatar_photo_form_photo_label"
);
const select_avatar_submit = document.getElementById(
    "select_avatar_photo_form_submit"
);
const select_avatar_label_value = document.getElementById(
    "select_avatar_photo_form_photo_label"
).innerText;
const current_avatar = document.getElementById("current_avatar");

select_avatar.addEventListener("change", function () {
    let file_chosen = false;
    if (this.files) {
        file_chosen = true;
        if (this.files[0].type.startsWith("image/")) {
            let reader = new FileReader();
            reader.onload = function (e) {
                current_avatar.style.backgroundImage = "url(" + e.target.result + ")";
            };
            reader.readAsDataURL(this.files[0]);
        }
    }

    if (file_chosen) {
        select_avatar_label.innerText = photo_selected_text;
        select_avatar_submit.classList.remove("hidden");
    } else {
        select_avatar_label.innerText = select_avatar_label_value;
        select_avatar_submit.classList.add("hidden");
    }
});
