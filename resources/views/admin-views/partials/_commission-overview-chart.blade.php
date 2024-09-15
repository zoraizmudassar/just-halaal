@php($params = session('dash_params'))
<div id="grow-sale-chart"></div>

<script>
    var options = {
          series: [{
          name: 'Gross Sale',
          data: [{{ implode(",",$data['total_sell']) }}]
        },{
          name: 'Admin Comission',
          data: [{{ implode(",",$data['commission']) }}]
        },{
          name: 'Delivery Comission',
          data: [{{ implode(",",$data['delivery_commission']) }}]
        }],
          chart: {
          height: 350,
          type: 'area',
          toolbar: {
            show:false
        },
            colors: ['#76ffcd','#ff6d6d', '#FF9B2E'],
        },
            colors: ['#76ffcd','#ff6d6d', '#FF9B2E'],
        dataLabels: {
          enabled: false,
            colors: ['#76ffcd','#ff6d6d', '#FF9B2E'],
        },
        stroke: {
          curve: 'smooth',
          width: 2,
            colors: ['#76ffcd','#ff6d6d', '#FF9B2E'],
        },
        fill: {
            type: 'gradient',
            colors: ['#76ffcd','#ff6d6d', '#FF9B2E'],
        },
        xaxis: {
        //   type: 'datetime',
            categories: [{!! implode(",",$data['label']) !!}]
        },
        tooltip: {
          x: {
            format: 'dd/MM/yy HH:mm'
          },
        },
        };

        var chart = new ApexCharts(document.querySelector("#grow-sale-chart"), options);
        chart.render();
    </script>

    <script>
        // INITIALIZATION OF CHARTJS
        // =======================================================
        Chart.plugins.unregister(ChartDataLabels);

        $('.js-chart').each(function () {
            $.HSCore.components.HSChartJS.init($(this));
        });

        var updatingChart = $.HSCore.components.HSChartJS.init($('#updatingData'));
    </script>
