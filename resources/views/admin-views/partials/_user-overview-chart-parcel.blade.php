@php($params = session('dash_params'))

<div class="position-relative pie-chart">
    <div id="dognut-pie"></div>
    <!-- Total Orders -->
    <div class="total--orders">
        <h3 class="text-uppercase mb-xxl-2">{{ $data['customer'] + $data['delivery_man'] }}</h3>
        <span class="text-capitalize">{{translate('messages.total_users')}}</span>
    </div>
    <!-- Total Orders -->
</div>
<div class="d-flex flex-wrap justify-content-center mt-4">
    <div class="chart--label">
        <span class="indicator chart-bg-1"></span>
        <span class="info">
            {{translate('messages.customer')}} {{$data['customer']}}
        </span>
    </div>
    <div class="chart--label">
        <span class="indicator chart-bg-3"></span>
        <span class="info">
            {{translate('messages.delivery_man')}} {{$data['delivery_man']}}
        </span>
    </div>
</div>


<script>
    var options = {
        series: [{{ $data['customer']}}, {{$data['delivery_man']}}],
        chart: {
            width: 320,
            type: 'donut',
        },
        labels: ['{{ translate('Customer') }}', '{{ translate('Delivery man') }}'],
        dataLabels: {
            enabled: false,
            style: {
                colors: ['#FF9B2E',  '#b9e0e0',]
            }
        },
        responsive: [{
            breakpoint: 1650,
            options: {
                chart: {
                    width: 250
                },
            }
        }],
        colors: ['#FF9B2E', '#111'],
        fill: {
            colors: ['#FF9B2E', '#b9e0e0']
        },
        legend: {
            show: false
        },
    };

    var chart = new ApexCharts(document.querySelector("#dognut-pie"), options);
    chart.render();

</script>

<!-- Dognut Pie Chart -->
    <script>
        // INITIALIZATION OF CHARTJS
        // =======================================================
        Chart.plugins.unregister(ChartDataLabels);

        $('.js-chart').each(function () {
            $.HSCore.components.HSChartJS.init($(this));
        });

        var updatingChart = $.HSCore.components.HSChartJS.init($('#updatingData'));
    </script>
