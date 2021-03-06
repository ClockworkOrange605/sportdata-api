<?php 
    namespace SportData\Clients\BetBoom;

    use Illuminate\Http\Client\PendingRequest as HttpClient;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    class BetBoom
    {
        private $client;

        public function __construct()
        {
            $this->client = new HttpClient();
            $this->client->baseUrl(env('BETBOOM_HOST'));
            $this->client->withOptions([
                'json' => [
                    'partnerId' => env('BETBOOM_PARTNER_ID'),
                    'langId' => env('BETBOOM_LANGUAGE_ID'),
                    'timeFilter' => 0,
                ]
            ]);
        }

        public function getSports() : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Common/GetSportsListFull');

            $sports = array_map(function($sport) {
                return (object) [
                    'name' => $sport['N'],
                    'source' => (object) [
                        'id' => $sport['Id'],
                        'class' => self::class
                    ]
                ];
            }, $response->json());

            return collect($sports);
        }

        public function getCountriesWithLeagues(
            int $sportId, 
            string $dateFrom = '', 
            string $dateUntil = '' ) : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Events/GetResChampsList', [
                "sp" => $sportId,
                'st' => !empty($dateFrom) ? $dateFrom : "2018-01-01T00:00:00.000Z",
                'en' => !empty($dateUntil) ? $dateUntil : "2020-12-31T23:59:59.999Z",
            ]);

            $list = collect(
                array_map(function($item) {
                    return (object) [
                        'country_id' => (int) (string) Str::of($item['K'])->after('l')->before('_g'),
                        'league_id' => (int) (string) Str::of($item['K'])->after('_g'),
                        'name' => trim(str_replace('&nbsp;', ' ', $item['V'])),
                    ];
                }, $response->json())
            );

            $countries = $list->where('league_id', 0);
            $leagues = $list->where('league_id', '!=', 0)->groupBy('country_id');
            
            $result = [];
            $countries->each(function($item) use($countries, $leagues, &$result) {
                array_push($result, (object) [
                    'name' => $item->name,
                    'source' => (object) [
                        'id' => $item->country_id,
                        'type' => self::class,
                    ],
                    'leagues' => collect(
                        array_map(function($league) {
                            return (object) [
                                'name' => $league->name,
                                'source' => (object) [
                                    'id' => $league->league_id,
                                    'type' => self::class,
                                ]
                            ];
                        }, $leagues->get($item->country_id)->toArray())
                    ),
                ]);
            });

            return collect($result);
        }

        public function getEvents(
            int $sportId, 
            int $countryId = 0, 
            int $leagueId = 0,
            string $dateFrom = '', 
            string $dateUntil = '', 
            string $query = '' ) : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Events/GetResults', [
                's' => $sportId,
                'l' => $countryId,
                'g' => $leagueId,
                'sdtSting' => !empty($dateFrom) ? $dateFrom : "2018-01-01T00:00:00.000Z",
                'edtSting' => !empty($dateUntil) ? $dateUntil : "2020-12-31T23:59:59.999Z",
                'evF' => $query,
            ]);

            $events = array_map(function($league) {
                return (object) [
                    'league' => (object) [
                        'name' => $league['N'],
                        'source' => (object) [
                            'id' => $league['Id'],
                            'type' => self::class,
                        ]
                    ],
                    'events' => collect(
                        self::mapEvents($league['E'])
                    )                    
                ];
            }, $response->json());

            return collect($events);
        }

        public function getSportsWithPrematchEvents() : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Events/GetSportsWithCount');

            $countries = array_map(function($country) { 
                return (object) [
                    'name' => $country['N'],
                    'source' => (object) [
                        'id' => $country['Id'],
                        'class' => self::class
                    ]
                ];
            }, $response->json());

            return collect($countries);
        }

        public function getCountriesWithPrematchEvents(int $sportId) : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Common/GetCountryList', [
                'sportId' => $sportId,
            ]);

            $countries = array_map(function($country) { 
                return (object) [
                    'name' => $country['N'],
                    'source' => (object) [
                        'id' => $country['Id'],
                        'class' => self::class
                    ]
                ];
            }, $response->json());

            return collect($countries);
        }

        public function getLeaguesWithPrematchEvents(int $countryId) : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Common/GetChampsList', [
                'countryId' => $countryId,
            ]);

            $leagues = array_map(function($league) {
                return (object) [
                    'name' => $league['N'],
                    'source' => (object) [
                        'id' => $league['Id'],
                        'class' => self::class
                    ]
                ];
            }, $response->json());

            return collect($leagues);
        }
        
        public function getPrematchEvents(int $leagueId) : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Events/GetEventsList', [
                'champId' => $leagueId
            ]);

            return collect(
                self::mapEventsWithOdds($response->json())
            );
        }

        public function getPrematchEventOdds(int $eventId) : \Illuminate\Support\Collection
        {
            $response = $this->client->post('Events/GetEvent', [
                'eventId' => $eventId
            ]);

            return collect(
                self::mapEventsWithOdds($response->json())
            );
        }

        public function getLiveEvents(int $sportId, array $oddTypes = [1,2,3]) : \Illuminate\Support\Collection
        {
            $response = $this->client->get('Live/GetEventsList', [
                'sportId' => $sportId,
                'stTypes' => $oddTypes,
            ]);

            return collect(
                self::mapEventsWithOdds($response->json())
            );
        }

        public function getLiveEventOdds(int $eventId) : \Illuminate\Support\Collection
        {
            $response = $this->client->get('Live/GetEventStakes', [
                'eventNumber' => $eventId
            ]);

            return collect(
                self::mapEventsWithOdds($response->json())
            );
        }

        private static function mapEvents(array $array) : array
        {
            return array_map(function($event) {
                return (object) [
                    'name' => $event['N'],
                    'status' => $event['S'],
                    'status' => Str::of($event['S']) != 'Canceled' ? 'finished' : 'canceled',

                    'date' => self::prepareDate($event['D']),
                    'scores' => (string) Str::of($event['S'])->before('<br />'),
                    'teams' => (object) [
                        'home' => (object) [
                            'name' => (string) Str::of($event['N'])->before('-')->trim(),
                            'score' => (int) (string) Str::of($event['S'])
                                ->before('<br />')->before(' ')->before(':'),
                        ],
                        'away' => (object) [
                            'name' => (string) Str::of($event['N'])->after('-')->trim(),
                            'score' => (int) (string) Str::of($event['S'])
                                ->before('<br />')->before(' ')->after(':'),
                        ],
                    ],
                    'odds' => collect(
                        self::mapOddValues($event['Stakes'])
                    ),
                    'source' => (object) [
                        'id' => $event['Id'],
                        'class' => self::class
                    ]
                ];
            }, $array);
        }

        private static function mapEventsWithOdds(array $array) : array
        {
            return array_map(function($event) {
                return (object) [
                    'name' => $event['N'],
                    'date' => $event['D'],
                    'date' => self::prepareDate($event['D']),
                    'period_name' => trim($event['S']),
                    'period_time' => $event['PT'],
                    'scores' => $event['SS'],
                    'sport' => (object) [
                        'name' => $event['SN'],
                        'source' => (object) [
                            'id' => $event['SId'],
                            'class' => self::class
                        ]
                    ],
                    'country' => (object) [
                        'name' => $event['CtN'],
                    ],
                    'league' => (object) [
                        'name' => $event['CN'], 
                        'source' => (object) [
                            'id' => $event['CId'],
                            'class' => self::class
                        ]
                    ],
                    'teams' => (object) [
                        'home' => (object) [
                            'name' => $event['HT'],
                            'score' => $event['HS'],
                        ],
                        'away' => (object) [
                            'name' => $event['AT'],
                            'score' => $event['AS'],
                        ],
                    ],
                    'odds' => collect(
                        self::mapOdds($event['StakeTypes'])
                    ),
                    'source' => (object) [
                        'id' => $event['Id'],
                        'class' => self::class
                    ],
                    'additional' => (object) [
                        'BS' => $event['BS'], // Bet Stakes (Betting Active)
                        'CR' => $event['CR'], // Угловые
                        'McId' => $event['McId'], // LiveScores ID
                    ],
                    'unknown' => (object) [
                        'LSId' => $event['LSId'],
                        'Fid' => $event['Fid'],
                        'OC' => $event['OC'],
                        'ScN' => $event['ScN'],
                        'ScPId' => $event['ScPId'],
                        'Srv' => $event['Srv'],
                    ],
                ];
            }, $array);
        }

        private static function mapOdds(array $array) : array
        {
            return array_map(function($odd_type) {
                return (object) [
                    'name' => $odd_type['N'],
                    'values' => collect(
                        self::mapOddValues($odd_type['Stakes'])
                    ),
                    'source' => (object) [
                        'id' => $odd_type['Id'],
                        'class' => self::class
                    ],
                    'additional' => (object) [
                        "A" => $odd_type["A"],
                        "BP" => $odd_type["BP"],
                        "Groups" => $odd_type["Groups"],
                        "IsA" => $odd_type["IsA"],
                        "IsC" => $odd_type["IsC"],
                        "PP" => $odd_type["PP"],
                        "RP" => $odd_type["RP"],
                    ]
                ];
            }, $array);
        }

        private static function mapOddValues(array $array) : array
        {
            return array_map(function($odd_value) {
                return (object) [ 
                    'name' => $odd_value['N'],
                    'term' => $odd_value['A'],
                    'value' => $odd_value['F'],
                    'is_winner' => $odd_value['IsWinner'],
                    'source' => (object) [
                        'id' => $odd_value['Id'],
                        'class' => self::class
                    ],
                    'original' => (object) $odd_value
                ];
            }, $array);
        }

        private static function prepareDate(string $date) : string
        {
            return (string) Carbon::createFromFormat('Y-m-d\TH:s:i\Z', $date);
        }
    }