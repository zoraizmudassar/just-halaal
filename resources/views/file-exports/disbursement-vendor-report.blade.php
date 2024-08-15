
<div class="row">
    @php($address = \App\Models\BusinessSetting::where(['key' => 'address'])->first()->value)
    <table>
        <thead>
            <tr>

                <th>
                    {{ translate('Disbursement_report') }}
                </th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th>

                </th>
            </tr>
            <tr>

                <th>{{ translate('filter_criteria') }} -</th>
                <th></th>
                <th>
                    <br>
                    @if ($data['from'])
                        <br>
                        {{ translate('from' )}} - {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                        <br>
                        {{ translate('to' )}} - {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif
                    <br>
                    {{ translate('filter')  }}- {{  translate($data['filter']) }}
                    <br>
                    {{ translate('Search_Bar_Content')  }}- {{ $data['search'] ??translate('N/A') }}
                    <br>
                    {{ translate('status')  }}: {{ $data['status'] ?? translate('N/A') }}

                </th>
                <th></th>
                <th></th>
                <th>

                </th>
            </tr>
            <tr>

                <th>
                {{ translate('Pending_Disbursements') }} - {{ $data['pending'] ?? translate('N/A') }}
                </th>
                <th></th>
                <th>{{ translate('Completed_Disbursements') }} - {{ $data['completed'] ?? translate('N/A') }}
                </th>
                <th></th>
                <th>{{ translate('Canceled_Transactions') }} - {{ $data['canceled'] ?? translate('N/A') }}
                </th>
                <th>

                </th>
            </tr>
        <tr>
            <th>{{ translate('sl') }}</th>
            <th>{{ translate('id') }}</th>
            <th>{{ translate('created_at') }}</th>
            <th>{{ translate('amount') }}</th>
            <th>{{ translate('Payment_method') }}</th>
            <th>{{ translate('status') }}</th>

        </thead>
        <tbody>
        @foreach($data['disbursements'] as $key => $disb)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $disb['disbursement_id'] }}</td>
        <td>{{ \App\CentralLogics\Helpers::time_date_format($disb['created_at']) }}</td>
        <td>
            {{\App\CentralLogics\Helpers::format_currency($disb['disbursement_amount'])}}
        </td>
        <td>
            <div class="name">{{translate('payment_method')}} : {{$disb->withdraw_method->method_name}}</div>
            @forelse(json_decode($disb->withdraw_method->method_fields, true) as $key=> $item)
            <br>
                <div>
                    <span>{{  translate($key) }}</span>
                    <span>:</span>
                    <span class="name">{{$item}}</span>
                </div>

            @empty

            @endforelse
        </td>
        <td>{{ $disb['status'] }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
</div>
