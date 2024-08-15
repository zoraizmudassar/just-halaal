<?php

namespace App\Http\Controllers\Admin\System;

use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\SMS_module;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Foundation\Application;
use Modules\Gateways\Traits\SmsGateway;

class AddonController extends Controller
{
    public function __construct(){
        if (is_dir('Modules\Gateways\Traits') && trait_exists('Modules\Gateways\Traits\SmsGateway')) {
            $this->extendWithSmsGatewayTrait();
        }
    }

    private function extendWithSmsGatewayTrait()
    {
        $extendedControllerClass = $this->generateExtendedControllerClass();
        eval($extendedControllerClass);
    }

    private function generateExtendedControllerClass()
    {
        $baseControllerClass = get_class($this);
        $traitClassName = 'Modules\Gateways\Traits\SmsGateway';

        $extendedControllerClass = "
            class ExtendedController extends $baseControllerClass {
                use $traitClassName;
            }
        ";

        return $extendedControllerClass;
    }

    public function index(): Factory|View|Application
    {
        $dir = 'Modules';
        $directories = self::getDirectories($dir);
        $addons = [];
        foreach ($directories as $directory) {
            $sub_dirs = self::getDirectories('Modules/' . $directory);
            if (in_array('Addon', $sub_dirs)) {
                $addons[] = 'Modules/' . $directory;
            }
        }
        return view('admin-views.system.addon.index', compact('addons'));
    }

    public function publish(Request $request): JsonResponse|int
    {
        $full_data = include($request['path'] . '/Addon/info.php');
        $path = $request['path'];
        $addon_name = $full_data['name'];
        if ($full_data['purchase_code'] == null || $full_data['username'] == null) {
            return response()->json([
                'flag' => 'inactive',
                'view' => view('admin-views.system.addon.partials.activation-modal-data', compact('full_data', 'path', 'addon_name'))->render(),
            ]);
        }
        $full_data['is_published'] = $full_data['is_published'] ? 0 : 1;
        $str = "<?php return " . var_export($full_data, true) . ";";
        file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

        return response()->json([
            'status' => 'success',
            'message'=> 'status_updated_successfully'
        ]);
    }

    public function activation(Request $request): Redirector|RedirectResponse|Application
    {
        $remove = ["http://", "https://", "www."];
        $url = str_replace($remove, "", url('/'));
        $full_data = include($request['path'] . '/Addon/info.php');

		$full_data['is_published'] = 1;
		$full_data['username'] = $request['username'];
		$full_data['purchase_code'] = $request['purchase_code'];
		$str = "<?php return " . var_export($full_data, true) . ";";
		file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

		Toastr::success(translate('activated_successfully'));
		return back();
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_upload' => 'required|mimes:zip'
        ]);

        if ($validator->errors()->count() > 0) {
            $error = Helpers::error_processor($validator);
            return response()->json(['status' => 'error', 'message' => $error[0]['message']]);
        }

        $file = $request->file('file_upload');
        $filename = $file->getClientOriginalName();
        $tempPath = $file->storeAs('temp', $filename);
        $zip = new \ZipArchive();

        if ($zip->open(storage_path('app/' . $tempPath)) === TRUE) {
            // Extract the contents to a directory
            $extractPath = base_path('Modules/');
            $zip->extractTo($extractPath);
            $zip->close();
            if(File::exists($extractPath.'/'.explode('.', $filename)[0].'/Addon/info.php')){
                File::chmod($extractPath.'/'.explode('.', $filename)[0].'/Addon', 0777);
                Toastr::success(translate('file_upload_successfully!'));
                $status = 'success';
                $message = translate('file_upload_successfully!');
            }else{
                File::deleteDirectory($extractPath.'/'.explode('.', $filename)[0]);
                $status = 'error';
                $message = translate('invalid_file!');
            }
        }else{
            $status = 'error';
            $message = translate('file_upload_fail!');
        }

        Storage::delete($tempPath);

        return response()->json([
            'status' => $status,
            'message'=> $message
        ]);
    }

    public function delete_theme(Request $request){
        $path = $request->path;

        $full_path = base_path($path);

        if(File::deleteDirectory($full_path)){
            return response()->json([
                'status' => 'success',
                'message'=> translate('file_delete_successfully')
            ]);
        }else{
            return response()->json([
                'status' => 'error',
                'message'=> translate('file_delete_fail')
            ]);
        }

    }

    //helper functions
    function getDirectories(string $path): array
    {
        $directories = [];
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item == '..' || $item == '.')
                continue;
            if (is_dir($path . '/' . $item))
                $directories[] = $item;
        }
        return $directories;
    }
}
