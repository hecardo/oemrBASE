<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\Header;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Send Text</title>
    <?php Header::setupHeader(['common']); ?>
</head>
<body>
<div class="container-fluid m-2 main_container">
    <h1>Send Text</h1>
    <form id="text_form" action='../../public/index.php/texting/individualPatient' method="post">
        <input type="hidden" name="phone" value="<?php echo $_GET['phone']; ?>">
        <textarea class="form-control col-6 mb-2" name="messageoutbound"></textarea>
        <button id="my-form-button" class="form-control col-2 mb-2"><?php echo xlt('Send'); ?></button>
    </form>
    <p id="my-form-status"></p>
</div>
<script>
    const form = document.getElementById("text_form");

    async function handleSubmit(event) { event.preventDefault();
        const status = document.getElementById("my-form-status");
        const data = new FormData(event.target);
        fetch(event.target.action, {
            method: form.method,
            body: data,
            headers: {
                'Accept': 'application/json'
            }
        }).then(function(response)  {
            if (response.ok) {
                let reply;
                response.text().then(function(text) {
                    status.innerHTML = text;
                    });
                form.reset()
            } else {
                response.json().then(data => {
                    if (Object.hasOwn(data, 'errors')) {
                        status.innerHTML = data["errors"].map(error => error["message"]).join(", ")
                    } else {
                        status.innerHTML = "Oops! There was a problem submitting your form"
                    }
                })
            }
        }).catch(error => {
            status.innerHTML = "Oops! There was a problem submitting your form"});
    }
    form.addEventListener("submit", handleSubmit)
</script>

</body>
</html>
