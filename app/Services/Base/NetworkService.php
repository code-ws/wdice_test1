<?php
/**
 * Created by PhpStorm.
 * User: cly
 * Date: 31/05/2019
 * Time: 06:27
 */

namespace App\Services;

use App\Models\GlobalConfig;
use App\Models\NetworkAccount;
use App\Models\NetworkWorth;
use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NetworkService
{
    protected $lineIds = [];
    protected $lineSuccessCount = 0;
    protected $lineFailedCount = 0;

    protected $country_error = 0;

    protected $fromAdUnit = [];

    public function fetchLineConfig($line_id, $country, HttpService $service, $retry = 0)
    {
        if ($retry == 0 && isset($this->lineIds[$line_id]))
            return;
        $this->lineIds[$line_id] = 1;
        $service->request("https://app.mopub.com/web-client/api/line-items/get", "get")
            ->addParam("key", $line_id)
            ->setCallback(function ($content, $code) use ($line_id, $retry, $service, $country) {
                if ($code == 200) {
                    $data = json_decode($content, true);
                    $date = GlobalConfig::getDate();
                    $item = NetworkWorth::query()->where('line_id', $line_id)->where('date', $date)
                        ->first();
                    if ($item == null) {
                        $item = new NetworkWorth();
                    }
                    if (isset($data['cpm'])) {
                        $item['worth'] = $data['cpm'];
                    } else if (isset($data['autoCpm']) && isset($data['autoCpm']['cpm'])) {
                        $item['worth'] = $data['autoCpm']['cpm'];
                    } else {
                        $item['worth'] = $data['bid'];
                    }
                    $item['network_name'] = $data['advertiser'];
                    //单独处理chartboost的network id
                    if (strtolower($item['network_name']) == 'chartboost') {
                        $item['network_id'] = $data['overrideFields']['location'] ?? null;
                    } else {
                        $item['network_id'] = $data['overrideFields']['network_adunit_id'];
                    }

                    //单独处理hyprmx的network id
                    if ($item['network_id'] === null || strlen($item['network_id']) === 0) {
                        try {
                            $custom_data = json_decode($data['overrideFields']['custom_event_class_data'], true);
                            $item['network_id'] = $custom_data['distributorID'];
                        } catch (\Exception $e) {
                            Log::info($data['overrideFields']);
                        }
                    }
                    $item['line_id'] = $line_id;
                    $item['country'] = $country;
                    $item['date'] = GlobalConfig::getDate();
                    $item->save();
                    $this->lineSuccessCount++;
                    echo "success ";
                } else if ($retry < 4) {
                    $this->fetchLineConfig($line_id, $country, $service, $retry + 1);
                } else {
                    $this->lineFailedCount++;
                    echo "failed ";
                    $from = isset($this->fromAdUnit[$line_id]) ? $this->fromAdUnit[$line_id] : null;
                    JobService::saveLog(0, "fetch_line_config_failed", $line_id, $content, $from);
                }
            });
    }

    public function onlyLetter($country)
    {
        for ($i = 0; $i < strlen($country); $i++) {
            $c = ord($country[$i]);
            if ($c > ord('Z') || $c < ord('A')) {
                return false;
            }
        }
        return true;
    }

    public function syncWorth($type = null)
    {
        $accounts = NetworkAccount::query()->get()->toArray();
        foreach ($accounts as $account) {
            if ($type == 1) {
                $this->syncWorthWithAccount($account);
            } else if ($type == 2) {
                $this->fetchLineItems($account);
            } else {
                $this->syncWorthWithAccount($account);
                $this->fetchLineItems($account);
            }
        }
        $contents = ["同步Network cpm"];
        $contents[] = "failed:" . $this->lineFailedCount;
        $contents[] = "total:" . count($this->lineIds);
        $contents[] = "success:" . $this->lineSuccessCount;
        $contents[] = "country error:" . $this->country_error;

        if ($this->lineFailedCount > 0) {
            JobService::potatoManager(implode("\n", $contents), null, "error");
        } else if (LifeService::life("network_report", "")) {
            LifeService::reduceLife("network_report", "", 4);
            JobService::potatoManager(implode("\n", $contents));
        }

        Log::info($this->country_error);
        Log::info(count($this->lineIds));
        Log::info($this->lineSuccessCount . "/" . $this->lineFailedCount);
    }

    public function fetchLineItems($account)
    {
        //https://app.mopub.com/web-client/api/ad-units/get?key=b9fb72203594457caeffe9f1f1eca4ed&includeAdSources=true
        $serviceWithCookie = new HttpService();
        $cookieJar = CookieJar::fromArray([
            'sessionid' => $account['session_id']
        ], 'app.mopub.com');
        $serviceWithCookie->setCookie($cookieJar);
        $bundle_id = $account['bundle_id'];
        $results = DB::select("select distinct value from adunit_configs where bundle_id = '$bundle_id'");
        $lines = [];
        foreach ($results as $result) {
            $adunit_id = $result->value;
            $request = $serviceWithCookie->request("https://app.mopub.com/web-client/api/ad-units/get", "get")
                ->addParam("key", $adunit_id)
                ->addParam("includeAdSources", "true")
                ->setNetworkErrorRetry(3)
                ->setCallback(function ($content, $code) use (&$lines, $adunit_id) {
                    if ($adunit_id == '829f7debd8f84fdeaeca8a3b08c1b06b' || $adunit_id == 'fa55e74532a24809bab4a3fbc4a2298c' || $adunit_id == '3f25336774c74a89a65a489b5dc7eff1') {
                        echo $content . "," . $code . "\n";
                    }

                    if ($content == 'Invalid ad unit key')
                        return false;
                    try {
                        if ($code == 200) {
                            $data = json_decode($content, true);
                            $items = $data['adSources'];
                            foreach ($items as $item) {
                                if (strtolower($item['status']) == 'paused') {
                                    continue;
                                }
                                $lineId = $item['key'];
                                $line_name = $item['name'];
                                if (strtolower($line_name) == strtolower('MarketPlace'))
                                    continue;
                                $array = explode("_", $line_name);
                                $country = strtoupper($array[count($array) - 1]);
                                if (strlen($country) > 3 || $this->onlyLetter($country) == false) {
                                    //Log::info($lineId.",".$country);
                                    $country = 'US';
                                    $this->country_error++;
                                } else if ($country == 'UK') {
                                    $country = "GB";
                                    $this->country_error++;
                                }
                                $lines[] = ["id" => $lineId, "country" => $country, "from" => $adunit_id];
                                $this->fromAdUnit[$lineId] = $adunit_id;
                            }
                            return ["success" => true, "data" => $lines];
                        } else {
                            Log::info($adunit_id);
                            Log::info($content);
                            return ["success" => false];
                        }
                    } catch (\Exception $e) {
                        dump($e);
                    }

                });
        }
        $serviceWithCookie->waiting();

        if (count($lines)) {
            $index = 0;
            foreach ($lines as $pack) {
                $lineId = $pack['id'];
                $country = $pack['country'];
                $this->fetchLineConfig($lineId, $country, $serviceWithCookie);
                $serviceWithCookie->waiting();
                echo $index . "\n";
                $index++;
                sleep(1);
            }
        }
    }

    public function syncWorthWithAccount($account)
    {
        $serviceWithCookie = new HttpService();
        $serviceWithCookie->concurrency = 5;
        $cookieJar = CookieJar::fromArray([
            'sessionid' => $account['session_id']], 'app.mopub.com');
        $serviceWithCookie->setCookie($cookieJar);

        $date = Carbon::now()->addDay(-2)->format('Y-m-d');
        Log::info($date);
        $service = new HttpService();
        $service->setTimeOut(60);
        $service->request("https://app.mopub.com/reports/custom/api/download_report", "get")
            ->addParam("report_key", $account['report_key'])
            ->addParam("api_key", $account['api_key'])
            ->addParam("date", $date)
            ->setCallback(function ($content, $code) use ($account) {
                Log::info($code);
                //Log::info($content);
                if ($code == 200) {
                    $data = explode("\n", $content);
                    $lines = [];
                    $index = 0;
                    $name_index = 0;
                    $header = explode(",", $data[0]);
                    $linesSet = [];
                    for ($i = 0; $i < count($header); $i++) {
                        if ($header[$i] == "Line Item ID") {
                            $index = $i;
                        } else if ($header[$i] == "Line Item") {
                            $name_index = $i;
                        }
                    }

                    for ($i = 1; $i < count($data); $i++) {
                        $item = explode(",", $data[$i]);
                        if (count($item) <= $index) {
                            Log::info($data[$i]);
                        } else {
                            $lineId = $item[$index];
                            if (isset($linesSet[$lineId]))
                                continue;
                            $linesSet[$lineId] = 1;
                            $name = $item[$name_index];
                            $array = explode("_", $name);
                            $country = strtoupper($array[count($array) - 1]);
                            if (strlen($country) > 3 || $this->onlyLetter($country) == false) {
                                Log::info($lineId . "," . $country);
                                $country = 'US';
                                $this->country_error++;
                            } else if ($country == 'UK') {
                                $country = "GB";
                                $this->country_error++;
                            }
                            $lines[] = ["id" => $lineId, "country" => ($country)];
                        }
                    }
                    return ["success" => true, "data" => $lines];
                } else {
                    Log::info($content);
                    JobService::potatoManager("sync network worth failed \n account name " . $account['name']);
                    return ["success" => false];
                }
            });
        $service->waiting();
        $data = $service->retData();
        if ($data['success']) {
            $lines = $data['data'];
            foreach ($lines as $pack) {
                $lineId = $pack['id'];
                $country = $pack['country'];
                $this->fetchLineConfig($lineId, $country, $serviceWithCookie);
                $serviceWithCookie->waiting();
                sleep(1);
            }
        }
    }
}