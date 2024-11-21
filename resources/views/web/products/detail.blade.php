@extends('web.webapp')

@section('content')

    <!-- Single Page Header start -->
    <div class="container-fluid page-header py-5">
        <h1 class="text-center text-white display-6">{{ $product->name }}</h1>
        <ol class="breadcrumb justify-content-center mb-0">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/shop">{{ $store->name }}</a></li>
            <li class="breadcrumb-item active text-white">{{ $product->name }}</li>
        </ol>
    </div>
    <!-- Single Page Header End -->

    <!-- Single Product Detail Start -->
    <div class="container-fluid py-5 mt-5">
        <div class="container py-5">
            <div class="row g-4">
                <!-- Product Image -->
                <div class="col-lg-6">
                    <div class="border rounded">
                        <img
                        {{-- src="{{ url('web/img/food.jpg') }}" --}}
                        src="{{ asset('storage/product/' . $product->image) }}"
                            onerror="this.onerror=null; this.src='https://placehold.co/600x600';"
                            class="img-fluid rounded w-100" alt="{{ $product->name }}">
                    </div>
                </div>

                <!-- Product Info -->
                <div class="col-lg-6">
                    <h4 class="fw-bold mb-3">{{ $product->name }}</h4>
                    <p class="mb-3"><strong>Category:</strong> {{ $product->category->name }}</p>
                    <h5 class="fw-bold mb-3">${{ number_format($product->price, 2) }}</h5>
                    <div class="d-flex mb-4">
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="fa fa-star {{ $i <= $product->avg_rating ? 'text-warning' : 'text-secondary' }}"></i>
                        @endfor
                    </div>
                    <p class="mb-4">{{ $product->description }}</p>
                    <p class="mb-4"><strong>Availability:</strong> {{ $product->stock > 0 ? 'In Stock' : 'Out of Stock' }}
                    </p>

                    <!-- Variants -->
                    @if ($addons->isNotEmpty())
                        <h6 class="mb-3"><strong>AddOns:</strong></h6>
                        <form id="variant-form">
                            @foreach ($addons as $variant)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="variant"
                                        id="variant-{{ $variant->id }}" value="{{ $variant->id }}">
                                    <label class="form-check-label" for="variant-{{ $variant->id }}">
                                        {{ $variant->name }} - ${{ number_format($variant->price, 2) }}
                                    </label>
                                </div>
                            @endforeach
                        </form>
                    @endif
                    @php
                        // Decode the JSON string if it's a string, or use the existing value
                        $variations = is_string($product->food_variations)
                            ? json_decode($product->food_variations)
                            : $product->food_variations;
                    @endphp

                    @if (!empty($variations))
                        <form id="variant-form">
                            @foreach ($variations as $variant)
                                <h6 class="mb-3"><strong> Variants:</strong></h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="variant"
                                        id="variant-{{ $variant->name }}" value="{{ $variant->name }}">
                                    <label class="form-check-label" for="variant-{{ $variant->name }}">
                                        {{ $variant->name }}
                                    </label>

                                    <!-- Iterate through the `values` array -->
                                    @foreach ($variant->values as $info)
                                        <div class="form-check">
                                            {{-- <p>Price: ${{ $info->optionPrice }}</p>
                                <p>Label: {{ $info->label }}</p> --}}
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </form>
                    @endif



                    <!-- Quantity Selector -->
                    {{-- <div class="input-group quantity my-4" style="width: 120px;">
                        <button class="btn btn-outline-secondary btn-sm" id="btn-minus">-</button>
                        <div class="form-control text-center" id="quantity-display"
                            style="user-select: none; cursor: default;">
                            1
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" id="btn-plus">+</button>
                    </div> --}}


                    <!-- Add to Cart Button -->
                    <!-- Add to Cart Button -->
<form action="{{ route('cart.add') }}" method="POST">
    @csrf <!-- Include CSRF token for Laravel -->

    <!-- Quantity Selector -->
    <div class="input-group quantity my-4" style="width: 120px;">
        <input type="number" name="quantity" class="form-control d-inline w-auto me-2" value="1" min="1">
    </div>

    <!-- Addons and Variations -->
    <input type="hidden" name="addons" id="addons-input">
    <input type="hidden" name="variations" id="variations-input">

    <!-- Product ID -->
    <input type="hidden" name="item_id" value="{{ $product->id }}">

    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2">
        <i class="fa fa-shopping-cart me-2"></i> Add to Cart
    </button>
</form>

                </div>
            </div>

            <!-- Additional Details Section -->
            <div class="row mt-5">
                <div class="col-lg-12">
                    <nav>
                        <div class="nav nav-tabs">
                            <button class="nav-link active" data-bs-toggle="tab"
                                data-bs-target="#description-tab">Description</button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews-tab">Reviews</button>
                        </div>
                    </nav>
                    <div class="tab-content mt-4">
                        <!-- Description Tab -->
                        <div class="tab-pane fade show active" id="description-tab">
                            <p>{{ $product->description }}</p>
                            <ul>
                                <li><strong>Weight:</strong> {{ $product->weight ?? 'N/A' }}</li>
                                <li><strong>Quality:</strong> {{ $product->quality ?? 'Standard' }}</li>
                                <li><strong>Origin:</strong> {{ $product->origin ?? 'Unknown' }}</li>
                            </ul>
                        </div>

                        <!-- Reviews Tab -->
                        <div class="tab-pane fade" id="reviews-tab">
                            @forelse ($product->reviews as $review)
                                <div class="d-flex mb-4">
                                    <img src="https://placehold.co/60x60" class="rounded-circle me-3" alt="User">
                                    <div>
                                        <h6>{{ $review->user->name }} -
                                            <small>{{ $review->created_at->format('M d, Y') }}</small>
                                        </h6>
                                        <div class="d-flex mb-2">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <i
                                                    class="fa fa-star {{ $i <= $review->rating ? 'text-warning' : 'text-secondary' }}"></i>
                                            @endfor
                                        </div>
                                        <p>{{ $review->comment }}</p>
                                    </div>
                                </div>
                            @empty
                                <p>No reviews yet.</p>
                            @endforelse
                            <form action="#" method="POST">
                                @csrf
                                <h6 class="mt-4">Leave a Review</h6>
                                <textarea class="form-control mb-3" rows="4" placeholder="Write your review"></textarea>
                                <button class="btn btn-primary" type="submit">Submit Review</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <h2 class="fw-bold mt-5">Related Products</h2>
            <div class="row g-4 mt-4">
                @foreach ($relatedProducts as $related)
                    <div class="col-md-4">
                        <div class="card">
                            <img
                            {{-- src="{{ url('web/img/food.jpg') }}" --}}
                            src="{{ asset('storage/product/' . $related->image) }}"
                                onerror="this.onerror=null; this.src='https://placehold.co/600x600';" class="card-img-top"
                                alt="{{ $related->name }}">
                            <div class="card-body">
                                <h5 class="card-title">{{ $related->name }}</h5>
                                <p class="card-text">${{ number_format($related->price, 2) }}</p>
                                <a href="{{ route('home.show', $related->id) }}" class="btn btn-outline-primary">View
                                    Details</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <!-- Single Product Detail End -->
    <script>
        // Get elements
        const quantityDisplay = document.getElementById("quantity-display");
        const btnMinus = document.getElementById("btn-minus");
        const btnPlus = document.getElementById("btn-plus");

        // Set initial quantity
        let quantity = 1;
        const minQuantity = 1;

        // Decrease value on minus button click
        btnMinus.addEventListener("click", () => {
            if (quantity > minQuantity) {
                quantity--;
                quantityDisplay.textContent = quantity;
            }
        });

        // Increase value on plus button click
        btnPlus.addEventListener("click", () => {
            quantity++;
            quantityDisplay.textContent = quantity;
        });

        document.addEventListener('DOMContentLoaded', function () {
        const addonsInput = document.getElementById('addons-input');
        const variationsInput = document.getElementById('variations-input');

        // Collect addons when checked
        const addonCheckboxes = document.querySelectorAll('input[name="variant"]:checked');
        addonCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateAddonsAndVariations);
        });

        // Function to update hidden inputs
        function updateAddonsAndVariations() {
            const selectedAddons = [];
            const selectedVariations = [];

            // Collect addon IDs
            document.querySelectorAll('input[name="variant"]:checked').forEach(checkbox => {
                selectedAddons.push(checkbox.value);
            });

            // Collect variation IDs (if applicable)
            document.querySelectorAll('input[name="variant"]:checked').forEach(checkbox => {
                selectedVariations.push(checkbox.value);
            });

            // Update hidden input fields
            addonsInput.value = JSON.stringify(selectedAddons);
            variationsInput.value = JSON.stringify(selectedVariations);
        }

        // Initial call to populate hidden fields on page load
        updateAddonsAndVariations();
    });
    </script>
@if (session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '{{ session('error') }}',
        });
    });
</script>
@endif
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
@endsection
