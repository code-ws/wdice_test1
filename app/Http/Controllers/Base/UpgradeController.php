<?php

namespace App\Http\Controllers\Base;

use App\Services\Base\ABTestService;
use Illuminate\Http\Request;

class UpgradeController extends Controller
{
    const None = 0;
    const HotUpgrade = 1;
    const ForceUpgrade = 2;
    const SoftUpgrade = 3;

    public function check(Request $request)
    {
        $this->verifySignature($request);
        $udid = $request->input('udid');
        $bundle_id = $request->input('bundle_id');
        $version = $request->input('version');
        $abtest_tags = ABTestService::allocUdidIdTags($udid,$bundle_id,$version);
        return $this->success([
            'upgrade' => self::None,
            'abtest_tags' => $abtest_tags
            ]);
    }
}
