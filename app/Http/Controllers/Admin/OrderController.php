<?php

namespace App\Http\Controllers\Admin;

use App\Mail\OrderVerificationMail;
use App\Mail\PlaceOrder;
use App\Mail\UserOfflinePaymentMail;
use App\Models\Item;
use App\Models\Zone;
use App\Models\Order;
use App\Models\Store;
use App\Models\Coupon;
use App\Models\Refund;
use App\Models\Category;
use App\Scopes\ZoneScope;
use App\Scopes\StoreScope;
use App\Models\DeliveryMan;
use App\Models\OrderDetail;
use App\Models\Translation;
use App\Exports\OrderExport;
use App\Mail\RefundRejected;
use App\Models\ItemCampaign;
use App\Models\RefundReason;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\CouponLogic;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\ProductLogic;
use App\CentralLogics\CustomerLogic;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Exports\StoreOrderlistExport;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use MatanYadaev\EloquentSpatial\Objects\Point;

class OrderController extends Controller
{
    public function list($status, Request $request)
    {
        // dd($status);
        $key = explode(' ', $request['search']);
        if (session()->has('zone_filter') == false) {
            session()->put('zone_filter', 0);
        }
        $module_id = $request->query('module_id', null);
        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
        }
        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store'])
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('store', function ($q) use ($request) {
                    return $q->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->whereRaw('created_at <> schedule_at');
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'pending', function ($query) {
                return $query->Pending();
            })
            ->when($status == 'accepted', function ($query) {
                return $query->AccepteByDeliveryman();
            })
            ->when($status == 'processing', function ($query) {
                return $query->Preparing();
            })
            ->when($status == 'item_on_the_way', function ($query) {
                return $query->ItemOnTheWay();
            })
            ->when($status == 'delivered', function ($query) {
                return $query->Delivered();
            })
            ->when($status == 'canceled', function ($query) {
                return $query->Canceled();
            })
            ->when($status == 'failed', function ($query) {
                return $query->failed();
            })
            ->when($status == 'refunded', function ($query) {
                return $query->Refunded();
            })
            ->when($status == 'requested', function ($query) {
                return $query->Refund_requested();
            })
            ->when($status == 'rejected', function ($query) {
                return $query->Refund_request_canceled();
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->Scheduled();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(($status != 'all' && $status != 'scheduled' && $status != 'canceled' && $status != 'rejected' && $status != 'requested' && $status != 'refunded' && $status != 'delivered' && $status != 'failed'), function ($query) {
                return $query->OrderScheduledIn(30);
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->orderStatus) && $status == 'all', function ($query) use ($request) {
                return $query->whereIn('order_status', $request->orderStatus);
            })
            ->when(isset($request->order_type), function ($query) use ($request) {
                return $query->where('order_type', $request->order_type);
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->when(isset($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->StoreOrder()
            ->module(Config::get('module.current_module_id'))
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));
        $orderstatus = isset($request->orderStatus) ? $request->orderStatus : [];
        $scheduled = isset($request->scheduled) ? $request->scheduled : 0;
        $vendor_ids = isset($request->vendor) ? $request->vendor : [];
        $zone_ids = isset($request->zone) ? $request->zone : [];
        $from_date = isset($request->from_date) ? $request->from_date : null;
        $to_date = isset($request->to_date) ? $request->to_date : null;
        $order_type = isset($request->order_type) ? $request->order_type : null;
        $total = $orders->total();


        return view('admin-views.order.list', compact('orders', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total', 'order_type'));
    }

    public function dispatch_list($module,$status, Request $request)
    {
        $module_id = $request->query('module_id', null);

        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
            $zone_ids = isset($request->zone) ? $request->zone : 0;
        }

        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store'])
            ->whereHas('module', function($query) use($module){
                $query->where('id', $module);
            })
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->StoreOrder()
            ->OrderScheduledIn(30)
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        $orderstatus = isset($request->orderStatus) ? $request->orderStatus : [];
        $scheduled = isset($request->scheduled) ? $request->scheduled : 0;
        $vendor_ids = isset($request->vendor) ? $request->vendor : [];
        $zone_ids = isset($request->zone) ? $request->zone : [];
        $from_date = isset($request->from_date) ? $request->from_date : null;
        $to_date = isset($request->to_date) ? $request->to_date : null;
        $total = $orders->total();

        return view('admin-views.order.distaptch_list', compact('orders','module', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total'));
    }

    public function details(Request $request, $id)
    {
        $order = Order::with(['details','offline_payments','refund', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id])->first();
        if (isset($order)) {
            if (isset($order->store)) {
                $deliveryMen = DeliveryMan::where('zone_id', $order->store->zone_id)
                ->where(function($query)use($order){
                            $query->where('vehicle_id',$order->dm_vehicle_id)->orWhereNull('vehicle_id');
                    })->available()->active()->get();
            } else {
                // $deliveryMen = isset($order->zone_id) ? DeliveryMan::where('zone_id', $order->zone_id)->zonewise()->available()->active()->get() : [];

                if($order->store !== null){
                    $deliveryMen = isset($order->zone_id) ? DeliveryMan::where('zone_id', $order->store->zone_id)->where(function($query)use($order){
                            $query->where('vehicle_id',$order->dm_vehicle_id)->orWhereNull('vehicle_id');
                    })
                    ->available()->active()->get():[];
                } else{
                    $deliveryMen = DeliveryMan::where('zone_id', '=', NULL)->where('vehicle_id',$order->dm_vehicle_id)->active()->get();
                }
            }
            $category = $request->query('category_id', 0);
            // $sub_category = $request->query('sub_category', 0);
            $categories = Category::active()->get();
            $keyword = $request->query('keyword', false);
            $key = explode(' ', $keyword);
            $products = Item::withoutGlobalScope(StoreScope::class)->where('store_id', $order->store_id)
                ->when($category, function ($query) use ($category) {
                    $query->whereHas('category', function ($q) use ($category) {
                        return $q->whereId($category)->orWhere('parent_id', $category);
                    });
                })
                ->when($keyword, function ($query) use ($key) {
                    return $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->paginate(10);
            $editing = false;
            if ($request->session()->has('order_cart')) {
                $cart = session()->get('order_cart');
                if (count($cart) > 0 && $cart[0]->order_id == $order->id) {
                    $editing = true;
                } else {
                    session()->forget('order_cart');
                }
            }

            $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);
            return view('admin-views.order.order-view', compact('order', 'deliveryMen', 'categories', 'products', 'category', 'keyword', 'editing'));
        } else {
            Toastr::info(translate('messages.no_more_orders'));
            return back();
        }
    }
    public function all_details(Request $request, $id)
    {
        $order = Order::with(['details','offline_payments' ,'refund', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id])->first();
        if (isset($order)) {
            if (isset($order->store)) {
                $deliveryMen = DeliveryMan::where('zone_id', $order->store->zone_id)->available()->active()->get();
            } else {
                $deliveryMen = isset($order->zone_id) ? DeliveryMan::where('zone_id', $order->zone_id)->zonewise()->available()->active()->get() : [];
            }
            $category = $request->query('category_id', 0);
            // $sub_category = $request->query('sub_category', 0);
            $categories = Category::active()->get();
            $keyword = $request->query('keyword', false);
            $key = explode(' ', $keyword);
            $products = Item::withoutGlobalScope(StoreScope::class)->where('store_id', $order->store_id)
                ->when($category, function ($query) use ($category) {
                    $query->whereHas('category', function ($q) use ($category) {
                        return $q->whereId($category)->orWhere('parent_id', $category);
                    });
                })
                ->when($keyword, function ($query) use ($key) {
                    return $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->paginate(10);
            $editing = false;
            if ($request->session()->has('order_cart')) {
                $cart = session()->get('order_cart');
                if (count($cart) > 0 && $cart[0]->order_id == $order->id) {
                    $editing = true;
                } else {
                    session()->forget('order_cart');
                }
            }

            $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);
            return view('admin-views.order.order-view', compact('order', 'deliveryMen', 'categories', 'products', 'category', 'keyword', 'editing'));
        } else {
            Toastr::info(translate('messages.no_more_orders'));
            return back();
        }
    }

    public function search(Request $request)
    {
        $key = explode(' ', $request['search']);
        $parcel_order = $request->parcel_order ?? false;
        $module_section_type = $request->module_section_type ?? false;
        $orders = Order::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('order_status', 'like', "%{$value}%")
                    ->orWhere('transaction_reference', 'like', "%{$value}%");
            }
        })->module(Config::get('module.current_module_id'));
        if ($module_section_type) {
            $orders = $orders->module($module_section_type);
        }
        if ($parcel_order) {
            $orders = $orders->withOutGlobalScope(ZoneScope::class)->ParcelOrder();
        } else {
            $orders = $orders->StoreOrder();
        }
        $orders = $orders->limit(50)->get();

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders', 'parcel_order'))->render()
        ]);
    }

    public function status(Request $request)
    {
        $request->validate([
            'reason'=>'required_if:order_status,canceled'
        ]);

        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->withOutGlobalScope(ZoneScope::class)->find($request->id);

        if (in_array($order->order_status, ['refunded', 'failed'])) {
            Toastr::warning(translate('messages.you_can_not_change_the_status_of_a_completed_order'));
            return back();
        }
        if (in_array($order->order_status, ['refund_requested']) && BusinessSetting::where(['key' => 'refund_active_status'])->first()->value == false) {
            Toastr::warning(translate('Refund Option is not active. Please active it from Refund Settings'));
            return back();
        }

        if ($order['delivery_man_id'] == null && $request->order_status == 'out_for_delivery') {
            Toastr::warning(translate('messages.please_assign_deliveryman_first'));
            return back();
        }

        if ($request->order_status == 'delivered' && $order['transaction_reference'] == null && $order['payment_method'] != 'cash_on_delivery') {
            Toastr::warning(translate('messages.add_your_paymen_ref_first'));
            return back();
        }

        if ($request->order_status == 'delivered') {

            if ($order->transaction  == null) {
                $unpaid_payment = OrderPayment::where('payment_status','unpaid')->where('order_id',$order->id)->first()?->payment_method;
                $unpaid_pay_method = 'digital_payment';
                if($unpaid_payment){
                    $unpaid_pay_method = $unpaid_payment;
                }
                if ($order->payment_method == "cash_on_delivery" || $unpaid_pay_method == 'cash_on_delivery') {
                    if ($order->order_type == 'take_away') {
                        $ol = OrderLogic::create_transaction($order, 'store', null);
                    } else if ($order->delivery_man_id) {
                        $ol =  OrderLogic::create_transaction($order, 'deliveryman', null);
                    } else if ($order->user_id) {
                        $ol =  OrderLogic::create_transaction($order, false, null);
                    }
                } else {
                    $ol = OrderLogic::create_transaction($order, 'admin', null);
                }
                if (!$ol) {
                    Toastr::warning(translate('messages.faield_to_create_order_transaction'));
                    return back();
                }
            } else if ($order->delivery_man_id) {
                $order->transaction->update(['delivery_man_id' => $order->delivery_man_id]);
            }

            $order->payment_status = 'paid';
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->increment('order_count');
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }
            $order->details->each(function ($item, $key) {
                if ($item->item) {
                    $item->item->increment('order_count');
                }
            });
            $order?->customer?->increment('order_count');
            if ($order->store) {
                $order->store->increment('order_count');
            }
            if ($order->parcel_category) {
                $order->parcel_category->increment('orders_count');
            }

            OrderLogic::update_unpaid_order_payment(order_id:$order->id, payment_method:$order->payment_method);

        } else if ($request->order_status == 'refunded' && BusinessSetting::where('key', 'refund_active_status')->first()->value == 1) {
            if ($order->payment_status == "unpaid") {
                Toastr::warning(translate('messages.you_can_not_refund_a_cod_order'));
                return back();
            }
            if (isset($order->delivered)) {
                $rt = OrderLogic::refund_order($order);
                if (!$rt) {
                    Toastr::warning(translate('messages.faield_to_create_order_transaction'));
                    return back();
                }
            }
            $refund_method = $request->refund_method  ?? 'manual';
            $wallet_status = BusinessSetting::where('key', 'wallet_status')->first()->value;
            $refund_to_wallet = BusinessSetting::where('key', 'wallet_add_refund')->first()->value;
            if ($order->payment_status == "paid" && $wallet_status == 1 && $refund_to_wallet == 1) {
                $refund_amount = round($order->order_amount - $order->delivery_charge - $order->dm_tips, config('round_up_to_digit'));
                CustomerLogic::create_wallet_transaction($order->user_id, $refund_amount, 'order_refund', $order->id);
                Toastr::info(translate('Refunded amount added to customer wallet'));
                $refund_method = 'wallet';
            } else {
                Toastr::warning(translate('Customer Wallet Refund is not active.Plase Manage the Refund Amount Manually'));
                $refund_method = $request->refund_method  ?? 'manual';
            }
            Refund::where('order_id', $order->id)->update([
                'order_status' => 'refunded',
                'admin_note' => $request->admin_note ?? null,
                'refund_status' => 'approved',
                'refund_method' => $refund_method,
            ]);
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }

            try {
                $mail_status = Helpers::get_mail_status('refund_order_mail_status_user');
                if(config('mail.status') && $order?->customer?->email && $mail_status == '1'){
                    Mail::to($order->customer->email)->send(new \App\Mail\RefundedOrderMail($order->id));
                }
            } catch (\Throwable $th) {
                info($th->getMessage());
                Toastr::error(translate('messages.Failed_to_send_mail'));
            }
        } else if ($request->order_status == 'canceled') {
            if (in_array($order->order_status, ['delivered', 'canceled', 'refund_requested', 'refunded', 'failed', 'picked_up']) || $order->picked_up) {
                Toastr::warning(translate('messages.you_can_not_cancel_a_completed_order'));
                return back();
            }
            $order->cancellation_reason = $request->reason;
            $order->canceled_by = 'admin';
            if (config('module.' . $order->module->module_type)['stock']) {
                foreach ($order->details as $detail) {
                    $variant = json_decode($detail['variation'], true);
                    $item = $detail->item;
                    if ($detail->campaign) {
                        $item = $detail->campaign;
                    }
                    ProductLogic::update_stock($item, -$detail->quantity, count($variant) ? $variant[0]['type'] : null)->save();
                }
            }
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }
            if($order->is_guest == 0){

                OrderLogic::refund_before_delivered($order);
            }
        }
        $order->order_status = $request->order_status;
        if($request->order_status == 'processing') {
            $order->processing_time = ($request?->processing_time) ? $request->processing_time : explode('-', $order['store']['delivery_time'])[0];
        }
        $order[$request->order_status] = now();
        $order->save();

        if (!Helpers::send_order_notification($order)) {
            Toastr::warning(translate('messages.push_notification_faild'));
        }

        Toastr::success(translate('messages.order_status_updated'));
        return back();
    }

    public function add_delivery_man($order_id, $delivery_man_id)
    {
        if ($delivery_man_id == 0) {
            return response()->json([
                'errors' => [
                    ['delivery_man_id' => translate('messages.deliveryman_not_found')]
                ]
            ], 404);
        }
        $order = Order::withOutGlobalScope(ZoneScope::class)->find($order_id);

        $deliveryman = DeliveryMan::where('id', $delivery_man_id)->available()->active()->first();
        if ($order->delivery_man_id == $delivery_man_id) {
            return response()->json([
                'errors' => [
                    ['delivery_man_id' => translate('messages.order_already_assign_to_this_deliveryman')]
                ]
            ], 400);
        }
        if ($deliveryman) {
            if ($deliveryman->current_orders >= config('dm_maximum_orders')) {
                return response()->json([
                    'errors' => [
                        ['current_orders' => translate('messages.dm_maximum_order_exceed_warning')]
                    ]
                ], 404);
            }
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();

                $data = [
                    'title' => 'Order Unassigned',
                    'description' => translate('messages.you_are_unassigned_from_a_order'),
                    'order_id' => '',
                    'image' => '',
                    'type' => 'assign'
                ];
                Helpers::send_push_notif_to_device($dm->fcm_token, $data);

                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'delivery_man_id' => $dm->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            $order->delivery_man_id = $delivery_man_id;
            $order->order_status = in_array($order->order_status, ['pending', 'confirmed']) ? 'accepted' : $order->order_status;
            $order->accepted = now();
            $order->save();

            $deliveryman->current_orders = $deliveryman->current_orders + 1;
            $deliveryman->save();
            $deliveryman->increment('assigned_order_count');

            $fcm_token= $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;
            $value = Helpers::order_status_update_message('accepted',$order->module->module_type,$order->customer?
            $order?->customer?->current_language_key:'en');
            $value = Helpers::text_variable_data_format(value:$value,store_name:$order->store?->name,order_id:$order->id,user_name:"{$order?->customer?->f_name} {$order?->customer?->l_name}",delivery_man_name:"{$order->delivery_man?->f_name} {$order->delivery_man?->l_name}");
            try {
                if ($value) {
                    $data = [
                        'title' => 'Status Updated',
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order_status'
                    ];

                    if($fcm_token){
                        Helpers::send_push_notif_to_device($fcm_token, $data);
                        DB::table('user_notifications')->insert([
                            'data' => json_encode($data),
                            'user_id' => $order?->customer?->id ,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
                $data = [
                    'title' => 'Order Assigned',
                    'description' => translate('messages.you_are_assigned_to_a_order'),
                    'order_id' => $order['id'],
                    'image' => '',
                    'type' => 'assign'
                ];
                Helpers::send_push_notif_to_device($deliveryman->fcm_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'delivery_man_id' => $deliveryman->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Exception $e) {
                info($e->getMessage());
                Toastr::warning(translate('messages.push_notification_faild'));
            }
            return response()->json([], 200);
        }
        return response()->json(['message' => 'Deliveryman not available!'], 400);
    }

    public function update_shipping(Request $request, Order $order)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
        ]);
        if ($request->latitude && $request->longitude) {
            $zone = Zone::where('id', $order->store->zone_id)->whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))->first();
            if (!$zone) {
                Toastr::error(translate('messages.out_of_coverage'));
                return back();
            }
        }
        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude
        ];

        $order->delivery_address = json_encode($address);
        $order->save();
        Toastr::success(translate('messages.delivery_address_updated'));
        return back();
    }

    public function generate_invoice($id)
    {
        $order = Order::withOutGlobalScope(ZoneScope::class)->with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where('id', $id)->first();
        return view('admin-views.order.invoice', compact('order'));
    }

    public function print_invoice($id)
    {
        $order = Order::withOutGlobalScope(ZoneScope::class)->with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where('id', $id)->first();
        return view('admin-views.order.invoice-print', compact('order'))->render();
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        $request->validate([
            'transaction_reference' => 'max:30'
        ]);
        Order::where(['id' => $id])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success(translate('messages.payment_reference_code_is_added'));
        return back();
    }

    public function add_order_proof(Request $request, $id)
    {
        if($request->order_proof == null ){
            Toastr::error(translate('messages.Must_select_an_Image'));
            return back();
        }

        $order = Order::find($id);
        $img_names = $order->order_proof?json_decode($order->order_proof):[];
        $images = [];
        $total_file = count($request->order_proof) + count($img_names);
        if(!$img_names){
            $request->validate([
                'order_proof' => 'required|array|max:5',
            ]);
        }

        if ($total_file>5) {
            Toastr::error(translate('messages.order_proof_must_not_have_more_than_5_item'));
            return back();
        }

        if (!empty($request->file('order_proof'))) {
            foreach ($request->order_proof as $img) {
                $image_name = Helpers::upload('order/', 'png', $img);
                array_push($img_names, $image_name);
            }
            $images = $img_names;
        }

        $order->order_proof = json_encode($images);
        $order->save();

        Toastr::success(translate('messages.order_proof_added'));
        return back();
    }
    public function remove_proof_image(Request $request)
    {
        $order = Order::find($request['id']);
        $array = [];
        $proof = isset($order->order_proof) ? json_decode($order->order_proof, true) : [];
        if (count($proof) < 2) {
            Toastr::warning(translate('all_image_delete_warning'));
            return back();
        }
        if (Storage::disk('public')->exists('order/' . $request['name'])) {
            Storage::disk('public')->delete('order/' . $request['name']);
        }
        foreach ($proof as $image) {
            if ($image != $request['name']) {
                array_push($array, $image);
            }
        }
        Order::where('id', $request['id'])->update([
            'order_proof' => json_encode($array),
        ]);
        Toastr::success(translate('order_proof_image_removed_successfully'));
        return back();
    }

    public function restaurnt_filter($id)
    {
        session()->put('restaurnt_filter', $id);
        return back();
    }

    public function filter(Request $request)
    {
        $request->validate([
            'from_date' => 'required_if:to_date,true',
            'to_date' => 'required_if:from_date,true',
        ]);
        session()->put('order_filter', json_encode($request->all()));
        return back();
    }
    public function filter_reset(Request $request)
    {
        session()->forget('order_filter');
        return back();
    }

    public function add_to_cart(Request $request)
    {
        if ($request->item_type == 'item') {
            $product = Item::find($request->id);
        } else {
            $product = ItemCampaign::find($request->id);
        }

        if (isset($product->module_id) && $product->module->module_type == 'food' && $product->food_variations) {
            $data = new OrderDetail();
            if ($request->order_details_id) {
                $data['id'] = $request->order_details_id;
            }

            $data['item_id'] = $request->item_type == 'item' ? $product->id : null;
            $data['item_campaign_id'] = $request->item_type == 'campaign' ? $product->id : null;
            $data['item'] = $request->item_type == 'item' ? $product : null;
            $data['item_campaign'] = $request->item_type == 'campaign' ? $product : null;
            $data['order_id'] = $request->order_id;
            $variations = [];
            $price = 0;
            $addon_price = 0;
            $variation_price = 0;

            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && count($product_variations)) {
                foreach ($request->variations  as $key => $value) {

                    if ($value['required'] == 'on' &&  isset($value['values']) == false) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select items from') . ' ' . $value['name'],
                        ]);
                    }
                    if (isset($value['values'])  && $value['min'] != 0 && $value['min'] > count($value['values']['label'])) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select minimum ') . $value['min'] . translate('For') . $value['name'] . '.',
                        ]);
                    }
                    if (isset($value['values']) && $value['max'] != 0 && $value['max'] < count($value['values']['label'])) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select maximum ') . $value['max'] . translate('For') . $value['name'] . '.',
                        ]);
                    }
                }
                $variation_data = Helpers::get_varient($product_variations, $request->variations);
                $variation_price = $variation_data['price'];
                $variations = $variation_data['variations'];
            }
            $price = $product->price + $variation_price;
            $data['variation'] = json_encode($variations);
            $data['variant'] = '';
            // $data['variation_price'] = $variation_price;
            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['status'] = true;
            $data['discount_on_item'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data["discount_type"] = "discount_on_product";
            $data["tax_amount"] = Helpers::tax_calculate($product, $price);
            $add_ons = [];
            $add_on_qtys = [];

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                    $add_on_qtys[] = $request['addon-quantity' . $id];
                }
                $add_ons = $request['addon_id'];
            }

            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withOutGlobalScope(StoreScope::class)->whereIn('id', $add_ons)->get(), $add_on_qtys);
            $data['add_ons'] = json_encode($addon_data['addons']);
            $data['total_add_on_price'] = $addon_data['total_add_on_price'];
            $cart = $request->session()->get('order_cart', collect([]));

            if (isset($request->cart_item_key)) {
                $cart[$request->cart_item_key] = $data;
                return response()->json([
                    'data' => 2
                ]);
            } else {
                $cart->push($data);
            }
        } else {

            $data = new OrderDetail();
            if ($request->order_details_id) {
                $data['id'] = $request->order_details_id;
            }

            $data['item_id'] = $request->item_type == 'item' ? $product->id : null;
            $data['item_campaign_id'] = $request->item_type == 'campaign' ? $product->id : null;
            $data['order_id'] = $request->order_id;
            $str = '';
            $price = 0;
            $addon_price = 0;

            //Gets all the choice values of customer choice option and generate a string like Black-S-Cotton
            foreach (json_decode($product->choice_options) as $key => $choice) {
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name]);
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name]);
                }
            }
            $data['variant'] = json_encode([]);
            $data['variation'] = json_encode([]);
            if ($request->session()->has('order_cart') && !isset($request->cart_item_key)) {
                if (count($request->session()->get('order_cart')) > 0) {
                    foreach ($request->session()->get('order_cart') as $key => $cartItem) {
                        // dd($cartItem);
                        if ($cartItem && $cartItem['item_id'] == $request['id'] && $cartItem['status'] == true) {
                            if (count(json_decode($cartItem['variation'], true)) > 0) {
                                if (json_decode($cartItem['variation'], true)[0]['type'] == $str) {
                                    return response()->json([
                                        'data' => 1
                                    ]);
                                }
                            } else {
                                return response()->json([
                                    'data' => 1
                                ]);
                            }
                        }
                    }
                }
            }
            //Check the string and decreases quantity for the stock
            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $vr = json_decode($product->variations);
                        $price = $vr[$i]->price;
                        $stock = isset($vr[$i]->stock) ? $vr[$i]->stock : 0;
                    }
                }
                $data['variation'] = json_encode([["type" => $str, "price" => $price, "stock" => $stock]]);
            } else {
                $price = $product->price;
            }

            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['status'] = true;
            $data['discount_on_item'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data["discount_type"] = "discount_on_product";
            $data["tax_amount"] = Helpers::tax_calculate($product, $price);
            $add_ons = [];
            $add_on_qtys = [];

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                    $add_on_qtys[] = $request['addon-quantity' . $id];
                }
                $add_ons = $request['addon_id'];
            }

            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withoutGlobalScope(StoreScope::class)->whereIn('id', $add_ons)->get(), $add_on_qtys);
            $data['add_ons'] = json_encode($addon_data['addons']);
            $data['total_add_on_price'] = $addon_data['total_add_on_price'];
            // dd($data);
            $cart = $request->session()->get('order_cart', collect([]));
            if (isset($request->cart_item_key)) {
                $cart[$request->cart_item_key] = $data;
                return response()->json([
                    'data' => 2
                ]);
            } else {
                $cart->push($data);
            }
        }
        return response()->json([
            'data' => 0
        ]);
    }

    public function remove_from_cart(Request $request)
    {
        $cart = $request->session()->get('order_cart', collect([]));
        $cart[$request->key]->status = false;
        $request->session()->put('order_cart', $cart);

        return response()->json([], 200);
    }

    public function edit(Request $request, Order $order)
    {
        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $order->id])->StoreOrder()->first();
        if ($request->cancle) {
            if ($request->session()->has(['order_cart'])) {
                session()->forget(['order_cart']);
            }
            return back();
        }
        $cart = collect([]);
        foreach ($order->details as $details) {
            unset($details['item_details']);
            $details['status'] = true;
            $cart->push($details);
        }

        if ($request->session()->has('order_cart')) {
            session()->forget('order_cart');
        } else {
            $request->session()->put('order_cart', $cart);
        }
        return back();
    }

    public function update(Request $request, Order $order)
    {
        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $order->id])->StoreOrder()->first();

        if (!$request->session()->has('order_cart')) {
            Toastr::error(translate('messages.order_data_not_found'));
            return back();
        }
        $cart = $request->session()->get('order_cart', collect([]));
        $store = $order->store;
        $coupon = null;
        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;
        if ($order->coupon_code) {
            $coupon = Coupon::where(['code' => $order->coupon_code])->first();
        }
        foreach ($cart as $c) {
            try {
                if ($c['status'] == true) {
                    unset($c['status']);
                    if ($c['item_campaign_id'] != null) {
                        $product = ItemCampaign::find($c['item_campaign_id']);
                        if ($product) {

                            $price = $c['price'];

                            $product = Helpers::product_data_formatting($product);

                            $c->item_details = json_encode($product);
                            $c->updated_at = now();
                            if (isset($c->id)) {
                                OrderDetail::where('id', $c->id)->update(
                                    [
                                        'item_id' => $c->item_id,
                                        'item_campaign_id' => $c->item_campaign_id,
                                        'item_details' => $c->item_details,
                                        'quantity' => $c->quantity,
                                        'price' => $c->price,
                                        'tax_amount' => $c->tax_amount,
                                        'discount_on_item' => $c->discount_on_item,
                                        'discount_type' => $c->discount_type,
                                        'variant' => $c->variant,
                                        'variation' => $c->variation,
                                        'add_ons' => $c->add_ons,
                                        'total_add_on_price' => $c->total_add_on_price,
                                        'updated_at' => $c->updated_at
                                    ]
                                );
                            } else {
                                $c->save();
                            }

                            $total_addon_price += $c['total_add_on_price'];
                            $product_price += $price * $c['quantity'];
                            $store_discount_amount += $c['discount_on_item'] * $c['quantity'];
                        } else {
                            Toastr::error(translate('messages.item_not_found'));
                            return back();
                        }
                    } else {
                        unset($c['item']);
                        unset($c['item_campaign']);
                        $product = Item::find($c['item_id']);
                        if ($product) {
                            $price = $c['price'];

                            $product = Helpers::product_data_formatting($product);

                            $c->item_details = json_encode($product);
                            $c->updated_at = now();
                            if (isset($c->id)) {
                                OrderDetail::where('id', $c->id)->update(
                                    [
                                        'item_id' => $c->item_id,
                                        'item_campaign_id' => $c->item_campaign_id,
                                        'item_details' => $c->item_details,
                                        'quantity' => $c->quantity,
                                        'price' => $c->price,
                                        'tax_amount' => $c->tax_amount,
                                        'discount_on_item' => $c->discount_on_item,
                                        'discount_type' => $c->discount_type,
                                        'variant' => $c->variant,
                                        'variation' => $c->variation,
                                        'add_ons' => $c->add_ons,
                                        'total_add_on_price' => $c->total_add_on_price,
                                        'updated_at' => $c->updated_at
                                    ]
                                );
                            } else {
                                $c->save();
                            }

                            $total_addon_price += $c['total_add_on_price'];
                            $product_price += $price * $c['quantity'];
                            $store_discount_amount += $c['discount_on_item'] * $c['quantity'];
                        } else {
                            Toastr::error(translate('messages.item_not_found'));
                            return back();
                        }
                    }
                } else {
                    $c->delete();
                }
            } catch (\Throwable $th) {
                info($th->getMessage());
            }
        }

        $store_discount = Helpers::get_store_discount($store);
        if (isset($store_discount)) {
            if ($product_price + $total_addon_price < $store_discount['min_purchase']) {
                $store_discount_amount = 0;
            }

            if ($store_discount_amount > $store_discount['max_discount'] && $store_discount_amount > $store_discount['max_discount']) {
                $store_discount_amount = $store_discount['max_discount'];
            }
        }
        $order->delivery_charge = $order->original_delivery_charge;
        if ($coupon) {
            if ($coupon->coupon_type == 'free_delivery') {
                $order->delivery_charge = 0;
                $coupon = null;
            }
        }

        if ($order->store->free_delivery || $order->order_type == 'take_away') {
            $order->delivery_charge = 0;
        }

        $coupon_discount_amount = $coupon ? CouponLogic::get_discount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;
        $total_price = $product_price + $total_addon_price - $store_discount_amount - $coupon_discount_amount;

        $tax = $store->tax;


        $order->tax_status = 'excluded';

        $tax_included = BusinessSetting::where(['key' => 'tax_included'])->first() ?  BusinessSetting::where(['key' => 'tax_included'])->first()->value : 0;
        if ($tax_included ==  1) {
            $order->tax_status = 'included';
        }

        $total_tax_amount = Helpers::product_tax($total_price, $tax, $order->tax_status == 'included');

        $total_tax_amount = $order->tax_status == 'included' ? 0 : $total_tax_amount;



        // $total_tax_amount = ($tax > 0) ? (($total_price * $tax) / 100) : 0;
        if ($store->minimum_order > $product_price + $total_addon_price) {
            Toastr::error(translate('messages.you_need_to_order_at_least', ['amount' => $store->minimum_order . ' ' . Helpers::currency_code()]));
            return back();
        }

        $free_delivery_over = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
        if (isset($free_delivery_over)) {
            if ($free_delivery_over <= $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount) {
                $order->delivery_charge = 0;
            }
        }


      //Added service charge
        $additional_charge_status = BusinessSetting::where('key', 'additional_charge_status')->first()->value;
        $additional_charge = BusinessSetting::where('key', 'additional_charge')->first()->value;
        if ($additional_charge_status == 1) {
            $order->additional_charge = $additional_charge ?? 0;
        } else {
            $order->additional_charge = 0;
        }



        $total_order_ammount = $total_price + $total_tax_amount + $order->delivery_charge + $order->additional_charge;
        $adjustment = $order->order_amount - $total_order_ammount;

        $order->coupon_discount_amount = $coupon_discount_amount;
        $order->store_discount_amount = $store_discount_amount;
        $order->total_tax_amount = $total_tax_amount;
        $order->order_amount = $total_order_ammount;
        $order->adjusment = $adjustment;
        $order->edited = true;
        $order->save();
        session()->forget('order_cart');
        Toastr::success(translate('messages.order_updated_successfully'));
        return back();
    }

    public function quick_view(Request $request)
    {

        $product = $product = Item::findOrFail($request->product_id);
        $item_type = 'item';
        $order_id = $request->order_id;

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.order.partials._quick-view', compact('product', 'order_id', 'item_type'))->render(),
        ]);
    }

    public function quick_view_cart_item(Request $request)
    {
        $cart_item = session('order_cart')[$request->key];
        $order_id = $request->order_id;
        $item_key = $request->key;
        $product = $cart_item->item ? $cart_item->item : $cart_item->campaign;
        $item_type = $cart_item->item ? 'item' : 'campaign';

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.order.partials._quick-view-cart-item', compact('order_id', 'product', 'cart_item', 'item_key', 'item_type'))->render(),
        ]);
    }

    public function export_orders($file_type, $status, $type, Request $request)
    {
        $key = explode(' ', $request['search']);

        if (session()->has('zone_filter') == false) {
            session()->put('zone_filter', 0);
        }

        $module_id = $request->query('module_id', null);

        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
        }

        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store'])
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('store', function ($q) use ($request) {
                    return $q->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->whereRaw('created_at <> schedule_at');
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'pending', function ($query) {
                return $query->Pending();
            })
            ->when($status == 'accepted', function ($query) {
                return $query->AccepteByDeliveryman();
            })
            ->when($status == 'processing', function ($query) {
                return $query->Preparing();
            })
            ->when($status == 'item_on_the_way', function ($query) {
                return $query->ItemOnTheWay();
            })
            ->when($status == 'delivered', function ($query) {
                return $query->Delivered();
            })
            ->when($status == 'canceled', function ($query) {
                return $query->Canceled();
            })
            ->when($status == 'failed', function ($query) {
                return $query->failed();
            })
            ->when($status == 'refunded', function ($query) {
                return $query->Refunded();
            })
            ->when($status == 'scheduled', function ($query) {
                return $query->Scheduled();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(($status != 'all' && $status != 'scheduled' && $status != 'canceled' && $status != 'refund_requested' && $status != 'refunded' && $status != 'delivered' && $status != 'failed'), function ($query) {
                return $query->OrderScheduledIn(30);
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->orderStatus) && $status == 'all', function ($query) use ($request) {
                return $query->whereIn('order_status', $request->orderStatus);
            })
            ->when(isset($request->scheduled) && $status == 'all', function ($query) {
                return $query->scheduled();
            })
            ->when(isset($request->order_type) && $type == 'order', function ($query) use ($request) {
                return $query->where('order_type', $request->order_type);
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->when($type == 'order', function ($query) {
                $query->StoreOrder();
            })
            ->when($type == 'parcel', function ($query) {
                $query->ParcelOrder();
            })
            ->when(isset($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->module(Config::get('module.current_module_id'))
            ->orderBy('schedule_at', 'desc')
            ->get();

            $data = [
                'orders'=>$orders,
                'type'=>$type,
                'status'=>$status,
                'order_status'=>isset($request->orderStatus)?implode(', ', $request->orderStatus):null,
                'search'=>$request->search??null,
                'from'=>$request->from_date??null,
                'to'=>$request->to_date??null,
                'zones'=>isset($request->zone)?Helpers::get_zones_name($request->zone):null,
                'stores'=>isset($request->vendor)?Helpers::get_stores_name($request->vendor):null,
            ];

        if ($file_type == 'excel') {
            return Excel::download(new OrderExport($data), 'Orders.xlsx');
        } else if ($file_type == 'csv') {
            return Excel::download(new OrderExport($data), 'Orders.csv');
        }
        // if ($file_type == 'excel') {
        //     return (new FastExcel(OrderLogic::format_export_data($orders, $type)))->download('Orders.xlsx');
        // } else if ($file_type == 'csv') {
        //     return (new FastExcel(OrderLogic::format_export_data($orders, $type)))->download('Orders.csv');
        // }
        return (new FastExcel(OrderLogic::format_export_data($orders, $type)))->download('Orders.xlsx');
    }

    public function store_order_search(Request $request)
    {
        $key = explode(' ', $request['search']);
        $orders = Order::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%");
            }
        })->limit(50)->get();

        return response()->json([
            'view' => view('admin-views.vendor.view.partials._order', compact('orders'))->render()
        ]);
    }
    public function store_order_export(Request $request)
    {
        $key = explode(' ', $request['search']);
        $orders = Order::where('store_id', $request->store_id)->Notpos()
            ->when(isset($key ), function ($q) use ($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->get();
        $store= Store::where('id', $request->store_id)->select(['id','zone_id'])->first();
        $data = [
            'data'=>$orders,
            'search'=>request()->search ?? null,
            'zone'=>Helpers::get_zones_name($store->zone_id) ,
            'store'=>  Helpers::get_stores_name($store->id),
        ];

        if($request->type == 'csv'){
            return Excel::download(new StoreOrderlistExport($data), 'OrderList.csv');
        }
        return Excel::download(new StoreOrderlistExport($data), 'OrderList.xlsx');



        // if ($type == 'excel') {
        //     return (new FastExcel(OrderLogic::format_export_data($orders, $type)))->download('Orders.xlsx');
        // } else if ($type == 'csv') {
        //     return (new FastExcel(OrderLogic::format_export_data($orders, $type)))->download('Orders.csv');
        // }
        // return (new FastExcel(OrderLogic::format_export_data($orders, $type)))->download('Orders.xlsx');
    }


    public function refund_settings()
    {
        $refund_active_status = BusinessSetting::where(['key' => 'refund_active_status'])->first();
        $reasons = RefundReason::orderBy('id', 'desc')
            ->paginate(config('default_pagination'));
        return view('admin-views.refund.index', compact('refund_active_status', 'reasons'));
    }

    public function refund_reason(Request $request)
    {
        $request->validate([
            'reason' => 'required|max:191',
            'reason.0' => 'required',
        ],[
            'reason.0.required'=>translate('default_reason_is_required'),
        ]);

        $reason = new RefundReason();
        $reason->reason = $request->reason[array_search('default', $request->lang)];
        $reason->save();
        $data = [];
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->reason[$index])){
                if ($key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\RefundReason',
                        'translationable_id' => $reason->id,
                        'locale' => $key,
                        'key' => 'reason',
                        'value' => $reason->reason,
                    ));
                }
            }else{
                if ($request->reason[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\RefundReason',
                        'translationable_id' => $reason->id,
                        'locale' => $key,
                        'key' => 'reason',
                        'value' => $request->reason[$index],
                    ));
                }
            }
        }
        Translation::insert($data);
        Toastr::success(translate('Refund Reason Added Successfully'));
        return back();
    }
    public function reason_edit(Request $request)
    {
        $request->validate([
            'reason' => 'required|max:191',
            'reason.0' => 'required',
        ],[
            'reason.0.required'=>translate('default_reason_is_required'),
        ]);
        $refund_reason = RefundReason::findOrFail($request->reason_id);
        $refund_reason->reason = $request->reason[array_search('default', $request->lang1)];
        $refund_reason->save();

        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang1 as $index => $key) {
            if($default_lang == $key && !($request->reason[$index])){
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\RefundReason',
                            'translationable_id' => $refund_reason->id,
                            'locale' => $key,
                            'key' => 'reason'
                        ],
                        ['value' => $refund_reason->reason]
                    );
                }
            }else{
                if ($request->reason[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\RefundReason',
                            'translationable_id' => $refund_reason->id,
                            'locale' => $key,
                            'key' => 'reason'
                        ],
                        ['value' => $request->reason[$index]]
                    );
                }
            }
        }


        Toastr::success(translate('Refund Reason Updated Successfully'));
        return back();
    }
    public function reason_delete(Request $request)
    {
        $refund_reason = RefundReason::findOrFail($request->id);
        $refund_reason?->translations()?->delete();
        $refund_reason->delete();
        Toastr::success(translate('Refund Reason Deleted Successfully'));
        return back();
    }
    public function reason_status(Request $request)
    {
        $refund_reason = RefundReason::findOrFail($request->id);
        $refund_reason->status = $request->status;
        $refund_reason->save();
        Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function order_refund_rejection(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'admin_note' => 'nullable|string|max:65535',
        ]);
        Refund::where('order_id', $request->order_id)->update([
            'order_status' => 'refund_request_canceled',
            'admin_note' => $request->admin_note ?? null,
            'refund_status' => 'rejected',
            'refund_method' => 'canceled',
        ]);

        $order = Order::Notpos()->find($request->order_id);
        $order->order_status = 'refund_request_canceled';
        $order->refund_request_canceled = now();
        $order->save();
        try {
            $mail_status = Helpers::get_mail_status('refund_request_deny_mail_status_user');
            if(config('mail.status') && $order?->customer?->email && $mail_status == '1'){
                Mail::to($order->customer->email)->send(new RefundRejected($order->id));
            }
        } catch (\Throwable $th) {
            info($th->getMessage());
            Toastr::error(translate('messages.Failed_to_send_mail'));
        }
        Toastr::success(translate('Refund Rejection Successfully'));
        Helpers::send_order_notification($order);
        return back();
    }


    public function refund_mode()
    {
        $refund_mode = BusinessSetting::where('key', 'refund_active_status')->first();
        if (isset($refund_mode) == false) {
            DB::table('business_settings')->insert([
                'key' => 'refund_active_status',
                'value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('business_settings')->where(['key' => 'refund_active_status'])->update([
                'key' => 'refund_active_status',
                'value' => $refund_mode->value == 1 ? 0 : 1,
                'updated_at' => now(),
            ]);
        }

        if (isset($refund_mode) && $refund_mode->value) {
            return response()->json(['message' => 'Order Refund Request Mode is off.']);
        }
        return response()->json(['message' => 'Order Refund Request Mode is on.']);
    }

    public function offline_payment(Request $request){
            $order=  Order::findOrFail($request->id);
            if($request->verify == 'yes'){

                $order->payment_status = 'paid';
                $order->confirmed = now();
                $order->order_status = 'confirmed';
                $order->save();
                Helpers::send_order_notification($order);
                $order->offline_payments()->update([
                    'status'=> 'verified'
                ]);

                $payment_method_name = json_decode($order->offline_payments->payment_info, true)['method_name'];
                if($order->payment_method == 'partial_payment'){
                    $order->payments()->where('payment_status','unpaid')->update([
                        'payment_method'=>  $payment_method_name,
                        'payment_status'=> 'paid',
                    ]);
                }
                $value = Helpers::text_variable_data_format(value:Helpers::order_status_update_message('offline_verified',$order->module->module_type),store_name:$order->store?->name,order_id:$order->id,user_name:"{$order?->customer?->f_name} {$order?->customer?->l_name}",delivery_man_name:"{$order?->delivery_man?->f_name} {$order?->delivery_man?->l_name}");
                $data = [
                    'title' => translate('messages.Your_Offline_payment_was_rejected'),
                    'description' => $value ??$request->note,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];

                $fcm= $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;
                if($fcm){
                    Helpers::send_push_notif_to_device($fcm, $data);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'user_id' => $order->user_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }


                $order->payment_method = $payment_method_name;
                    if($order->is_guest == 0){
                        $this->sent_mail_on_offline_payment(status:'approved', name:$order?->customer?->f_name .' '.$order?->customer?->l_name, email:  $order?->customer?->email , otp: $order->otp);
                    }
                }

            elseif($request->verify == 'switched_to_cod'){
                $order->offline_payments()->update([
                    'status'=> 'verified'
                ]);
                if($order->payment_method == 'partial_payment'){
                    $order->payments()->where('payment_status','unpaid')->update([
                        'payment_method'=> 'cash_on_delivery',
                    ]);
                }
                Helpers::send_order_notification($order);
                $order->payment_method = 'cash_on_delivery';

                if($order->is_guest == 0){
                    $this->sent_mail_on_offline_payment(status:'COD', name:$order?->customer?->f_name .' '.$order?->customer?->l_name, email:  $order?->customer?->email ,order_id: $order->id);
                }

            }

            else{
                $order->offline_payments()->update([
                    'status'=> 'denied',
                    'note'=> $request->note ?? null
                ]);


                $value = Helpers::text_variable_data_format(value:Helpers::order_status_update_message('offline_denied',$order->module->module_type),store_name:$order->store?->name,order_id:$order->id,user_name:"{$order?->customer?->f_name} {$order?->customer?->l_name}",delivery_man_name:"{$order?->delivery_man?->f_name} {$order?->delivery_man?->l_name}");

                    $data = [
                        'title' => translate('messages.Your_Offline_payment_was_rejected'),
                        'description' => $value ?? $request->note,
                        'order_id' => $order->id,
                        'image' => '',
                        'type' => 'order_status',
                    ];

                    $fcm= $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token ;
                    if($fcm){
                        Helpers::send_push_notif_to_device($fcm, $data);
                        DB::table('user_notifications')->insert([
                            'data' => json_encode($data),
                            'user_id' => $order->user_id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    if($order->is_guest == 0){
                        $this->sent_mail_on_offline_payment(status:'denied', name:$order?->customer?->f_name .' '.$order?->customer?->l_name, email:  $order?->customer?->email);
                    }
            }

            Toastr::success(translate('Payment_status_updated'));
            return back();
    }


    private function sent_mail_on_offline_payment($status, $name ,$email ,$otp=null ,$order_id = null){
        try
        {
            if($status == 'approved' && config('mail.status') ){

                if(Helpers::get_mail_status('offline_payment_approve_mail_status_user') == '1'){
                    Mail::to($email)->send(new UserOfflinePaymentMail($name, 'approved'));
                }
                $order_verification_mail_status = Helpers::get_mail_status('order_verification_mail_status_user');
                if ( $order_verification_mail_status == '1'  && $otp) {
                    Mail::to($email)->send(new OrderVerificationMail($otp, $name));
                }
            }

            if($status == 'COD' && $order_id  && config('mail.status'))
            {
                Mail::to($email)->send(new PlaceOrder($order_id));
            }
            if($status == 'denied' && config('mail.status') && Helpers::get_mail_status('offline_payment_deny_mail_status_user') == '1'){
                Mail::to($email)->send(new UserOfflinePaymentMail($name, 'denied'));
            }
        }
        catch(\Exception $e)
        {
            Toastr::error(translate('Failed_to_Send_Email'));
            info($e->getMessage());
            return true;
        }
        return true ;
    }

    public function offline_verification_list(Request $request, $status)
    {
        $key = explode(' ', $request['search']);
        $orders = Order::with(['customer', 'store'])->has('offline_payments')
            ->when(isset($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->when($status == 'pending' , function ($query) {
                return $query->whereHas('offline_payments', function ($query) {
                    return $query->where('status', 'pending');
                });
            })
            ->when($status == 'denied' , function ($query) {
                return $query->whereHas('offline_payments', function ($query) {
                    return $query->where('status', 'denied');
                });
            })
            ->when($status == 'verified' , function ($query) {
                return $query->whereHas('offline_payments', function ($query) {
                    return $query->where('status', 'verified');
                });
            })
            ->StoreOrder()
            ->module(Config::get('module.current_module_id'))
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        return view('admin-views.order.offline_verification_list', compact('orders', 'status'));
    }
}
