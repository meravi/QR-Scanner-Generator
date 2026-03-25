document.querySelectorAll("[data-copy-target]").forEach(function (button) {
    button.addEventListener("click", function () {
        var targetId = button.getAttribute("data-copy-target");
        var input = document.getElementById(targetId);

        if (!input) {
            return;
        }

        var resetLabel = button.textContent;

        function markCopied() {
            button.textContent = "Copied";
            window.setTimeout(function () {
                button.textContent = resetLabel;
            }, 1600);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(markCopied).catch(function () {
                input.focus();
                input.select();
                document.execCommand("copy");
                markCopied();
            });

            return;
        }

        input.focus();
        input.select();
        document.execCommand("copy");
        markCopied();
    });
});
