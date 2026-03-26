<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;

class WeatherController extends Controller
{
    private const LAT = '44.58';
    private const LON = '-0.27';

    /**
     * GET /api/meteo
     * Proxy vers WeatherAPI.com — la clé reste côté serveur.
     * Retourne : {"tmin":int,"tmax":int,"wind":int,"condition":string}
     */
    public function current(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $key = $_ENV['WEATHER_API_KEY'] ?? '';
        if ($key === '') {
            $this->json(['error' => 'no_key'], 503);
        }

        $url = 'https://api.weatherapi.com/v1/forecast.json'
            . '?key=' . urlencode($key)
            . '&q=' . self::LAT . ',' . self::LON
            . '&days=1'
            . '&lang=' . (isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'fr');

        $ctx = stream_context_create([
            'http' => [
                'timeout'        => 4,
                'ignore_errors'  => true,
                'user_agent'     => 'CrabitanBellevue/1.0',
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            $this->json(['error' => 'fetch_failed'], 502);
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['current'], $data['forecast'])) {
            $this->json(['error' => 'invalid_response'], 502);
        }

        $this->json([
            'tmin'      => (int) round($data['forecast']['forecastday'][0]['day']['mintemp_c'] ?? 0),
            'tmax'      => (int) round($data['forecast']['forecastday'][0]['day']['maxtemp_c'] ?? 0),
            'wind'      => (int) round($data['current']['wind_kph'] ?? 0),
            'condition' => $data['current']['condition']['text'] ?? '',
        ]);
    }
}
