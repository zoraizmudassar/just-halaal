@extends('web.webapp')

@section('content')

 <!-- Single Page Header start -->
 <div class="container-fluid page-header py-5">
    <h1 class="text-center text-white display-6">Cart</h1>
    <ol class="breadcrumb justify-content-center mb-0">
        <li class="breadcrumb-item"><a href="#">Home</a></li>
        <li class="breadcrumb-item"><a href="#">Pages</a></li>
        <li class="breadcrumb-item active text-white">Cart</li>
    </ol>
</div>
<!-- Single Page Header End -->

<!-- Cart Page Start -->
<div class="container-fluid py-5 " >
    <div class="container py-5">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Name</th>
                        <th scope="col">Price</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Total</th>
                        <th scope="col">Handle</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $cart = session()->get('cart', []); // Fetch cart from session
                        $subtotal = 0;
                    @endphp
                    @forelse ($cart as $itemId => $itemData)
                        @php
                            // Fetch product details based on ID
                            $product = \App\Models\Item::find($itemId);
                            $totalPrice = $product->price * $itemData['quantity'];
                            $subtotal += $totalPrice;
                        @endphp
                        <tr>
                            <th scope="row">
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('storage/product/' . $product->image) }}"
                                         class="img-fluid me-5 rounded-circle"
                                         style="width: 80px; height: 80px;"
                                         alt="{{ $product->name }}"
                                         onerror="this.onerror=null; this.src='https://placehold.co/80x80';">
                                </div>
                            </th>
                            <td>
                                <p class="mb-0 mt-4">{{ $product->name }}</p>
                                <!-- Variations -->
                                @if (!empty($itemData['variations']))
                                    <p class="small text-muted">
                                        <strong>Variations:</strong>
                                        {{ implode(', ', $itemData['variations']) }}
                                    </p>
                                @endif
                                <!-- Addons -->
                                @if (!empty($itemData['addons']))
                                    <p class="small text-muted">
                                        <strong>Addons:</strong>
                                        @foreach ($itemData['addons'] as $addonId)
                                            @php
                                                $addon = \App\Models\Addon::find($addonId);
                                            @endphp
                                            {{ $addon->name }} (${{ number_format($addon->price, 2) }})
                                        @endforeach
                                    </p>
                                @endif
                            </td>
                            <td>
                                <p class="mb-0 mt-4">${{ number_format($product->price, 2) }}</p>
                            </td>
                            <td>
                                <div class="input-group quantity mt-4" style="width: 100px;">
                                    <div class="input-group-btn">
                                        <button class="btn btn-sm btn-minus rounded-circle bg-light border" onclick="updateQuantity('{{ $itemId }}', {{ $itemData['quantity'] - 1 }})">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </div>
                                    <input type="text" class="form-control form-control-sm text-center border-0" value="{{ $itemData['quantity'] }}" readonly>
                                    <div class="input-group-btn">
                                        <button class="btn btn-sm btn-plus rounded-circle bg-light border" onclick="updateQuantity('{{ $itemId }}', {{ $itemData['quantity'] + 1 }})">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <td>
                                    <button class="btn btn-md rounded-circle bg-light border mt-4" onclick="removeFromCart('{{ $itemId }}')">
                                        <i class="fa fa-times text-danger"></i>
                                    </button>
                                </td>

                            </td>
                            <td>
                                <p class="mb-0 mt-4">${{ number_format($totalPrice, 2) }}</p>
                            </td>
                            <td>
                                <button class="btn btn-md rounded-circle bg-light border mt-4" onclick="removeFromCart('{{ $itemId }}')">
                                    <i class="fa fa-times text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Your cart is empty.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">
            <input type="text" class="border-0 border-bottom rounded me-5 py-3 mb-4" placeholder="Coupon Code">
            <button class="btn border-secondary rounded-pill px-4 py-3 text-primary" type="button">Apply Coupon</button>
        </div>
        <div class="row g-4 justify-content-end">
            <div class="col-8"></div>
            <div class="col-sm-8 col-md-7 col-lg-6 col-xl-4">
                <div class="bg-light rounded">
                    <div class="p-4">
                        <h1 class="display-6 mb-4">Cart <span class="fw-normal">Total</span></h1>
                        <div class="d-flex justify-content-between mb-4">
                            <h5 class="mb-0 me-4">Subtotal:</h5>
                            <p class="mb-0">${{ number_format($subtotal, 2) }}</p>
                        </div>
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0 me-4">Shipping</h5>
                            <div class="">
                                <p class="mb-0">Flat rate: $3.00</p>
                            </div>
                        </div>
                        <p class="mb-0 text-end">Shipping to Ukraine.</p>
                    </div>
                    <div class="py-4 mb-4 border-top border-bottom d-flex justify-content-between">
                        <h5 class="mb-0 ps-4 me-4">Total</h5>
                        <p class="mb-0 pe-4">${{ number_format($subtotal + 3, 2) }}</p>
                    </div>
                    <a href="/checkout" class="btn border-secondary rounded-pill px-4 py-3 text-primary text-uppercase mb-4 ms-4" type="button">Proceed Checkout</a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Cart Page End -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // Update Quantity
    function updateQuantity(itemId, quantity) {
        if (quantity < 1) return;

        $.ajax({
            url: '/cart/update',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}', // Include CSRF token
            },
            data: {
                item_id: itemId,
                quantity: quantity,
            },
            success: function (data) {
                if (data.success) {
                    location.reload(); // Reload page to reflect changes
                } else {
                    console.error(data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
            },
        });
    }

    // Remove Item from Cart
    function removeFromCart(itemId) {
        $.ajax({
            url: '/cart/remove',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}', // Include CSRF token
            },
            data: {
                item_id: itemId,
            },
            success: function (data) {
                if (data.success) {
                    location.reload(); // Reload page to reflect changes
                } else {
                    console.error(data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
            },
        });
    }
</script>



@endsection
