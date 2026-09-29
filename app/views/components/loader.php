<style>
    /* Full-screen absolute overlay, hidden by default */
    #ai-loader-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.95);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    /* The rotating CSS ring */
    .spinner {
        border: 8px solid #f3f3f3;
        border-top: 8px solid #0056b3;
        /* S.I.K.A.P. Blue */
        border-radius: 50%;
        width: 70px;
        height: 70px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .loader-text {
        margin-top: 20px;
        font-family: sans-serif;
        font-size: 1.2em;
        font-weight: bold;
        color: #2c3e50;
    }
</style>

<div id="ai-loader-overlay">
    <div class="spinner"></div>
    <div class="loader-text" id="loader-message">Processing AI Matrix...</div>
</div>

<script>
    // Function to physically reveal the overlay
    function showAILoader(message) {
        if (message) {
            document.getElementById('loader-message').innerText = message;
        }
        document.getElementById('ai-loader-overlay').style.display = 'flex';
    }

    // Auto-attach to any form strictly marked as an AI trigger
    document.addEventListener("DOMContentLoaded", function () {
        const forms = document.querySelectorAll('.ai-trigger-form');

        forms.forEach(form => {
            form.addEventListener('submit', function () {
                // Pull custom loading text from the form attribute, or use default
                let customMsg = form.getAttribute('data-loader-msg');
                showAILoader(customMsg || "Processing AI Matrix... Please wait.");
            });
        });
    });
</script>