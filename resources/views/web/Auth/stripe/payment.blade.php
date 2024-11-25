<!DOCTYPE html>
<html>
<head>
    <title>Stripe Payment</title>
    <!-- Include Stripe.js -->
    <script src="https://js.stripe.com/v3/"></script>
    <!-- Include any CSS styles -->
    <style>
        /* Add your custom styles here */
    </style>
</head>
<body>
    <h1>Stripe Payment Form</h1>

    <!-- Display success or error messages -->
    @if(session('success'))
        <div style="color: green;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="color: red;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Payment form -->
    <form id="payment-form" action="{{ route('payment.process') }}" method="POST">
        @csrf
        <div id="card-element">
            <!-- Stripe Element will be inserted here -->
        </div>

        <!-- Display form errors -->
        <div id="card-errors" role="alert" style="color: red;"></div>

        <button type="submit" id="submit-button">Pay $10</button>
    </form>

    <!-- Stripe Elements Script -->
    <script>
        // Set your publishable API key
        var stripe = Stripe('{{ config('services.stripe.key') }}');
        var elements = stripe.elements();

        // Custom styling
        var style = {
            base: {
                color: '#32325d',
                fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                fontSize: '16px',
            }
        };

        // Create card element
        var card = elements.create('card', {style: style});
        card.mount('#card-element');

        // Handle real-time validation errors
        card.on('change', function(event) {
            var displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });

        // Handle form submission
        var form = document.getElementById('payment-form');
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            stripe.createToken(card).then(function(result) {
                if (result.error) {
                    // Inform the user if there was an error
                    var errorElement = document.getElementById('card-errors');
                    errorElement.textContent = result.error.message;
                } else {
                    // Send the token to your server
                    stripeTokenHandler(result.token);
                }
            });
        });

        // Submit the form with the Stripe token
        function stripeTokenHandler(token) {
            // Insert the token into the form
            var hiddenInput = document.createElement('input');
            hiddenInput.setAttribute('type', 'hidden');
            hiddenInput.setAttribute('name', 'stripeToken');
            hiddenInput.setAttribute('value', token.id);
            form.appendChild(hiddenInput);

            // Submit the form
            form.submit();
        }
    </script>
</body>
</html>
