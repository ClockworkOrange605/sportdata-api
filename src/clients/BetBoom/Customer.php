<?php
    namespace SportData\Clients\BetBoom;

    use Illuminate\Http\Client\PendingRequest as HttpClient;
    // use GuzzleHttp\Cookie\FileCookieJar;

    class Customer
    {
        private $client;
        // private $cookies;

        public function __construct()
        {
            $this->client = new HttpClient;
            $this->client->baseUrl(env('BETBOOM_HOST'));

            // $this->cookies = new FileCookieJar('storage/cookie.json', true);
            // $this->client->withOptions([
            //     'cookies' =>$this->cookies,
            // ]);

            $this->client->withCookies([
                'ASP.NET_SesssionId' => env('BETBOOM_AUTH')
            ], 'sport.betboom.ru');

            
            // $this->client->withOptions([
            //     'json' => [
            //         'partnerId' => env('BETBOOM_PARTNER_ID'),
            //         'langId' => env('BETBOOM_LANGUAGE_ID'),
            //         'timeFilter' => 0,
            //     ]
            // ]);
        }

        public function index() 
        {
            $this->client->baseUrl('https://betboom.ru/sport');
            $response = $this->client->get('');
            $this->client->baseUrl(env('BETBOOM_HOST'));

            dump($response->body());
        }

        // public function authorize() 
        // {
        //     $response = $this->client->asForm()->post('https://betboom.ru/auth/login', [
        //         'phone' => env('BETBOOM_PHONE'),
        //         'password' => env('BETBOOM_PASSWORD'),
        //     ]);

        //     return $response->json('status') == 'success' ? true : false;
        // }

        public function addOdd($eventId, $oddId) 
        {
            $response = $this->client->post('Betting/AddStake', [
                // 'eventId' => 
                // 'stakeId' => 
                // 'matchIsLive' =>
                // 'isSuperTip' => 
                // 'partnerId' =>
            ]);

            dd($response);
        }

        public function setAmount(int $betAmount) 
        {
            $response = $this->client->post('https://sport.betboom.ru/Betting/SetBetAmount', [
                'amount' => $betAmount
            ]);

            dd($response->json());
        }

        public function getBalance()
        {
            // $response = $this->client->
        }

        public function makeBet($eventId, $oddId, $oddValue, $amount = 50)
        {
            $response = $this->client->post('Betting/QuickBet', [
                "eventId" => $eventId,
                "stakeId" => $oddId,
                "betAmount" => $amount,
                "fucktor" => $oddValue,
                "matchIsLive" => true,
                "isSuperTip" => false
            ]);

            $response = $response->json();

            return (object) [
                'coupon_id' => $response["OrderNumber"],
                'type' => $response['BetTypeName'],
                'amount' => $response['BetAmount'],               

                // +"SystemIndex": 0
                // +"TransactionId": 0
                // +"UserId": 6234221

                'status' => $response['StatusMessage']['success'],
                'status_code' => $response['StatusMessage']['Code'],
                'status_message' => $response['StatusMessage']['Status'],

                'error_code' => $response['StatusException']['ErrorCode'],
                'error_message' => $response['StatusException']['ErrorMessage'],

                // 'original' => (object) $response
            ];
        }

        public function getCoupon() {
            $response = $this->client->post('Betting/GetCoupon');

            // dump(
            //     $response->body(),
            //     $response->json(),
            // );

            // return (object) $response->json();
        }

        public function clearCoupon()
        {
            $response = $this->client->post('Betting/RemoveAllStakes');

            dd($response->json());
        }

        public function getOrder(string $orderId) 
        {
            $response = $this->client->post('Account/GetUserOrderBets', [
                "orderNumber" => $orderId,
                "gameType" => 0,
                "betNumber" => -1,
                "isRfId" => false
            ]);

            return (object) $response->json();
        }

        public function getOrders(string $fromDate, string $untilDate)
        {
            $response = $this->client->post('Account/GetUserOrders', [
                    "startDate" => $fromDate,
                    "endDate" => $untilDate,

                    "statusFilter" => 1,
                    "timeFilter" => 1,
                    "isDate" => true,
                    
                    "IsBetshopCash" => false,
                    "checkNumber" => "",
                    "disableCache" => false
                ]);

            $orders = array_map(function($order) {
                return (object) [
                    'id' => $order['N'],
                    'date' => $order['Dt'],
                    'bet_amount' => $order['BA'],
                    'win_amount' => $order['WA'],
                    // 'possible_win_amout' => $order['CW'],
                    
                    'is_win' => $order['W'],

                    'cancel_amount' => $order['CA'],

                    // $order['S'],
                    // $order['T'],

                    'orignal' => (object) $order
                ];
            }, $response->json());

            return collect($orders);
        }

    }