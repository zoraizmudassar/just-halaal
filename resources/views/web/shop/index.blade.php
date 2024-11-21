@extends('web.webapp')

@section('content')
    @php
        // dd($products)
    @endphp

    <!-- Single Page Header start -->
    <div class="container-fluid page-header py-5">
        <h1 class="text-center text-white display-6">{{ $store->name }}</h1>
        <ol class="breadcrumb justify-content-center mb-0">
            <li class="breadcrumb-item"><a href="#">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Pages</a></li>
            <li class="breadcrumb-item active text-white">{{ $store->name }}</li>
        </ol>
    </div>
    <!-- Single Page Header End -->


    <!-- Fruits Shop Start-->
    <div class="container-fluid fruite py-5">
        <div class="container py-5">
            <h1 class="mb-4">{{ $store->name }}</h1>
            <div class="row g-4">
                <div class="col-lg-12">
                    <div class="row g-4">
                        <div class="col-xl-3">
                            <form action="{{ route('home.restaurant', ['id' => $store->id]) }}" method="GET"
                                class="d-flex">
                                <input type="hidden" name="category_id" value="{{ $categoryId }}">
                                <input type="search" name="search" class="form-control p-3" placeholder="Keywords"
                                    value="{{ request('search') }}" aria-describedby="search-icon-1">
                                <button type="submit" class="input-group-text p-3" id="search-icon-1">
                                    <i class="fa fa-search"></i>
                                </button>
                            </form>
                        </div>
                        <div class="col-6"></div>
                        <div class="col-xl-3">
                            <div class="bg-light ps-3 py-3 rounded d-flex justify-content-between mb-4">
                                <label for="fruits">Categories:</label>
                                <select id="fruits" name="fruitlist" class="border-0 form-select-sm bg-light me-3"
                                    onchange="redirectToCategory(this)">
                                    <!-- "All Products" option -->
                                    <option value="{{ route('home.restaurant', ['id' => $store->id]) }}"
                                        {{ is_null($categoryId) ? 'selected' : '' }}>
                                        All Products
                                    </option>

                                    <!-- Loop through categories to create options -->
                                    @foreach ($categories as $category)
                                        <option
                                            value="{{ route('home.restaurant', ['id' => $store->id, 'category_id' => $category->id]) }}"
                                            {{ $categoryId == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>

                            </div>
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-3">
                            <div class="row g-4">
                                <div class="col-lg-12">
                                    <div class="mb-3">
                                        <h4>Categories</h4>
                                        <ul class="list-unstyled fruite-categorie">
                                            <li>
                                                <div class="d-flex justify-content-start ml-2">
                                                    <a href="{{ route('home.restaurant', ['id' => $store->id]) }}"
                                                        class="{{ is_null($categoryId) ? 'active-category' : '' }}">
                                                        All Products
                                                    </a>
                                                </div>
                                            </li>
                                            @foreach ($categories as $category)
                                                <li>
                                                    <div class="d-flex justify-content-between ">
                                                        <a href="{{ route('home.restaurant', ['id' => $store->id, 'category_id' => $category->id]) }}"
                                                            class="{{ $categoryId == $category->id ? '' : '' }}">
                                                            <i class=" me-2"></i>{{ $category->name }}
                                                        </a>
                                                        <span>({{ $products->where('category_id', $category->id)->count() }})</span>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="mb-3">
                                        <h4 class="mb-2">Price</h4>
                                        <form action="{{ route('home.restaurant', ['id' => $store->id]) }}" method="GET"
                                            id="priceFilterForm">
                                            <!--  hidden fields to persist category and search -->
                                            <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                                            <input type="hidden" name="search" value="{{ request('search') }}">

                                            <!-- Range Slider -->
                                            <input type="range" class="form-range w-100" id="rangeInput" name="max_price"
                                                min="0" max="500" value="{{ request('max_price', 500) }}"
                                                oninput="amount.value=rangeInput.value">

                                            <!-- Output to display selected price -->
                                            <output id="amount" name="amount">{{ request('max_price', 500) }}</output>

                                            <!-- Submit button for the filter -->
                                            <button type="submit" class="btn btn-primary mt-2">Filter</button>
                                        </form>
                                    </div>

                                </div>
                                {{-- <div class="col-lg-12">
                                    <div class="mb-3">
                                        <h4>Additional</h4>
                                        <div class="mb-2">
                                            <input type="radio" class="me-2" id="Categories-1" name="Categories-1"
                                                value="Beverages">
                                            <label for="Categories-1"> Organic</label>
                                        </div>
                                        <div class="mb-2">
                                            <input type="radio" class="me-2" id="Categories-2" name="Categories-1"
                                                value="Beverages">
                                            <label for="Categories-2"> Fresh</label>
                                        </div>
                                        <div class="mb-2">
                                            <input type="radio" class="me-2" id="Categories-3" name="Categories-1"
                                                value="Beverages">
                                            <label for="Categories-3"> Sales</label>
                                        </div>
                                        <div class="mb-2">
                                            <input type="radio" class="me-2" id="Categories-4" name="Categories-1"
                                                value="Beverages">
                                            <label for="Categories-4"> Discount</label>
                                        </div>
                                        <div class="mb-2">
                                            <input type="radio" class="me-2" id="Categories-5" name="Categories-1"
                                                value="Beverages">
                                            <label for="Categories-5"> Expired</label>
                                        </div>
                                    </div>
                                </div> --}}
                                {{-- <div class="col-lg-12">
                                    <h4 class="mb-3">Featured products</h4>
                                    <div class="d-flex align-items-center justify-content-start">
                                        <div class="rounded me-4" style="width: 100px; height: 100px;">
                                            <img src="web/img/featur-1.jpg" class="img-fluid rounded" alt="">
                                        </div>
                                        <div>
                                            <h6 class="mb-2">Big Banana</h6>
                                            <div class="d-flex mb-2">
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star"></i>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <h5 class="fw-bold me-2">2.99 $</h5>
                                                <h5 class="text-danger text-decoration-line-through">4.11 $</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-start">
                                        <div class="rounded me-4" style="width: 100px; height: 100px;">
                                            <img src="web/img/featur-2.jpg" class="img-fluid rounded" alt="">
                                        </div>
                                        <div>
                                            <h6 class="mb-2">Big Banana</h6>
                                            <div class="d-flex mb-2">
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star"></i>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <h5 class="fw-bold me-2">2.99 $</h5>
                                                <h5 class="text-danger text-decoration-line-through">4.11 $</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-start">
                                        <div class="rounded me-4" style="width: 100px; height: 100px;">
                                            <img src="web/img/featur-3.jpg" class="img-fluid rounded" alt="">
                                        </div>
                                        <div>
                                            <h6 class="mb-2">Big Banana</h6>
                                            <div class="d-flex mb-2">
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star text-secondary"></i>
                                                <i class="fa fa-star"></i>
                                            </div>
                                            <div class="d-flex mb-2">
                                                <h5 class="fw-bold me-2">2.99 $</h5>
                                                <h5 class="text-danger text-decoration-line-through">4.11 $</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-center my-4">
                                        <a href="#"
                                            class="btn border border-secondary px-4 py-3 rounded-pill text-primary w-100">Vew
                                            More</a>
                                    </div>
                                </div> --}}
                                <div class="col-lg-12">
                                    <div class="position-relative">
                                        <img src="web/img/banner-fruits.jpg" class="img-fluid w-100 rounded" alt="">
                                        <div class="position-absolute"
                                            style="top: 50%; right: 10px; transform: translateY(-50%);">
                                            <h3 class="text-secondary fw-bold">Fresh <br> Fruits <br> Banner</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-9">
                            <div class="row g-4 justify-content-start">
                                @if ($products->isEmpty())
                                    <!-- No Products Found Message -->
                                    <div class="col-12">
                                        <div class="text-center py-5">
                                            <h4 class="text-muted">No Products Found</h4>
                                        </div>
                                    </div>
                                @else
                                    <!-- Loop Through Products -->
                                    @foreach ($products as $product)
                                        <div class="col-md-6 col-lg-6 col-xl-4">
                                            <div class="rounded position-relative fruite-item">
                                                <div class="fruite-img">
                                                    <!-- Dynamically load the product image -->
                                                    <img
                                                    {{-- src="{{ url('web/img/res.jpg') }}" --}}
                                                    src="{{ asset('storage/product/' . $product->image) }}"
                                                        class="img-fluid w-100 rounded-top" alt="{{ $product->name }}">
                                                </div>
                                                <div class="text-white bg-secondary px-3 py-1 rounded position-absolute"
                                                    style="top: 10px; left: 10px;">
                                                    {{ $product->category->name ?? 'Category' }}
                                                    <!-- Replace with category name if available -->
                                                </div>
                                                <div class="p-4 border border-secondary border-top-0 rounded-bottom">
                                                    <h6>{{ $product->name }}</h6>
                                                    <p>{{ \Illuminate\Support\Str::words($product->description, 4) }}</p>
                                                    <div class="d-flex justify-content-between flex-lg-wrap">
                                                        <p class="text-dark fs-5 fw-bold mb-0">
                                                            €{{ number_format($product->price, 2) }}</p>
                                                        <a href="{{ route('home.show', $product->id) }}"
                                                            class="btn border border-secondary rounded-pill px-3 text-primary">
                                                            <i class="fa fa-shopping-bag me-2 text-primary"></i> View More
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    <!-- Pagination -->
                                    <div class="col-12">
                                        <div class="pagination d-flex justify-content-center mt-5">
                                            {{ $products->links('pagination::semantic-ui') }}
                                        </div>
                                    </div>
                                @endif
                            </div>


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Fruits Shop End-->
    <script>
        function redirectToCategory(select) {
            const url = select.value;
            if (url) {
                window.location.href = url;
            }
        }
    </script>
@endsection
