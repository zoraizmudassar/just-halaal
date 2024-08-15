@extends('payment-views.layouts.master')

@push('script')
    {{--stripe--}}
    <script src="https://polyfill.io/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>
    <script src="https://js.stripe.com/v3/"></script>
@endpush

<style>
    .loader1 {
	position: relative;
	margin: auto;
	width: 5rem;
	border-radius: 100vmin;
	overflow: hidden;
	padding: 1.25rem;
  
  &::before {
    content: "";
	  display: block;
	  padding-top: 100%;
  }
}

.circular1 {
	width: 100%;
	height: 100%;
	position: absolute;
	inset: 0;
	margin: auto;
	transform-origin: center center;
	animation: 2s linear 0s infinite rotate;
}

.path1 {
  stroke: yellow;
	stroke-dasharray: 1,200;
	stroke-dashoffset: 0;
	stroke-linecap: round; 
	animation: 1.5s ease-in-out 0s infinite dash;
}

@keyframes dash {
	0%{
		stroke-dasharray: 1,200;
		stroke-dashoffset: 0;
		
	}
	50% {
		stroke-dasharray: 89,200;
		stroke-dashoffset: -35px;
		stroke: yellow;
	}
	100% {
		stroke-dasharray: 89,200;
		stroke-dashoffset: -124px;
	}
}

@keyframes rotate {
	to {
		transform:rotate(1turn);
	}
}
</style>

@section('content')
<div class="loader1">
	<svg class='circula1r' viewbox='25 25 50 50'>
		<circle class='path1' cx='50' cy='50' r='20' fill='none' stroke-width='2' stroke-miterlimit='10' ></circle>
	</svg>
</div>

{{-- @php($config = payment_config('stripe', 'payment_config')) --}}
<script type="text/javascript">
    // Create an instance of the Stripe object with your publishable API key
    var stripe = Stripe('{{$config->published_key}}');
    document.addEventListener("DOMContentLoaded", function () {
        fetch("{{ url("payment/stripe/token/?payment_id={$data->id}") }}", {
            method: "GET",
        }).then(function (response) {
            console.log(response)
            return response.text();
        }).then(function (session) {
            console.log(session)
            return stripe.redirectToCheckout({sessionId: JSON.parse(session).id});
        }).then(function (result) {
            if (result.error) {
                alert(result.error.message);
            }
        }).catch(function (error) {
            console.error("error:", error);
        });
    });

</script>
@endsection
