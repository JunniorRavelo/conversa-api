<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DatosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function mapa()
    {

        //api datos bucaramanga
        $apiUrl = 'https://www.datos.gov.co/api/id/75fz-q98y.json?$query=select%20*%20where%20ano%20=%20%272021%27';

        // Realiza la solicitud a la API externa
        $response = Http::get($apiUrl);

        // Verifica si la solicitud fue exitosa
        if ($response->successful()) {
            // Filtra solo los datos de ubicación
            $ubicaciones = collect($response->json())
            ->filter(function ($item) {
                // Verifica que el elemento tenga latitud y longitud válidas
                return $item['latitud'] !== 'xx.xxxx' &&
                $item['longitud'] !== '-yy.yyyy';
            })
            ->map(function ($item) {
                return [
                    'latitud' => str_replace(',', '.', $item['latitud']),
                    'longitud' => str_replace(',', '.', $item['longitud']),
                ];
            })
            ->values();

            // Devuelve solo las ubicaciones en formato JSON
            return response()->json($ubicaciones);
        } else {
            // En caso de error, devuelve un mensaje de error
            return response()->json([
                'error' => 'No se pudo obtener los datos de la API externa.'
            ], $response->status());
        }
    }

    public function ambiente()
    {

        //api datos bucaramanga
        $apiUrl = 'https://www.datos.gov.co/api/id/75fz-q98y.json?$query=select%20*%20where%20ano%20=%20%272021%27';

        // Realiza la solicitud a la API externa
        $response = Http::get($apiUrl);

        // Verifica si la solicitud fue exitosa
        if ($response->successful()) {
            // Filtra solo los datos de ubicación
            $ubicaciones = collect($response->json())
            ->filter(function ($item) {
                // Verifica que el elemento tenga latitud y longitud válidas
                return $item['latitud'] !== 'xx.xxxx' &&
                $item['longitud'] !== '-yy.yyyy';
            })
            ->map(function ($item) {
                return [
                    'latitud' => str_replace(',', '.', $item['latitud']),
                    'longitud' => str_replace(',', '.', $item['longitud']),
                ];
            })
            ->values();

            // Devuelve solo las ubicaciones en formato JSON
            return response()->json($ubicaciones);
        } else {
            // En caso de error, devuelve un mensaje de error
            return response()->json([
                'error' => 'No se pudo obtener los datos de la API externa.'
            ], $response->status());
        }
    }


    public function microfono(Request $request)
    {
        // Recibe el mensaje del request
        $mensaje = $request->input('mensaje');

        try {
            // Configura las etiquetas de clasificación
            $labels = ['ambiente', 'seguridad'];

            // Crea una instancia de Guzzle para hacer la solicitud a la API
            $client = new Client();

            // Realiza la solicitud a la API de Hugging Face
                $response = $client->post('https://api-inference.huggingface.co/models/facebook/bart-large-mnli', [
                    'headers' => [
                        'Authorization' => 'Bearer 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', // Reemplaza con tu API Key de Hugging Face
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'inputs' => $mensaje,
                        'parameters' => [
                            'candidate_labels' => $labels,
                        ],
                    ],
                ]);

            // Procesa la respuesta de la API
            $result = json_decode($response->getBody(), true);

            // Obtén la etiqueta con mayor puntuación
            $label = $result['labels'][0];

            // Asigna un número basado en la etiqueta
            $numero = 0;
            if ($label === 'ambiente') {
                $numero = 1;

                $respuesta =  $this->generateText("Lloviendo ambiente malo");
                Log::info($respuesta);
            } elseif ($label === 'seguridad') {
                $numero = 2;
            }

            // Devuelve el número en la respuesta
            return response()->json(['numero' => $numero]);

        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json(['message' => 'Error en la clasificación de mensaje'], 500);
        }
    }


    public function generateText($mensaje = "")
    {

        Log::info(1);
        // Configura el cliente Guzzle
        $client = new Client();
        $apiUrl = "https://api-inference.huggingface.co/models/openai-community/gpt2";
        
        try {
            Log::info(2);
            // Define los headers y el payload
            $headers = [
                'Authorization' => 'Bearer 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',
                'Content-Type' => 'application/json',
            ];
            $payload = [
                'inputs' => $mensaje,
            ];

            // Realiza la solicitud POST a la API
            $response = $client->post($apiUrl, [
                'headers' => $headers,
                'json' => $payload,
            ]);

            Log::info($response->getBody());
            // Procesa la respuesta de la API
            $data = json_decode($response->getBody(), true);

            // Retorna el texto generado en JSON
            return response()->json(['generated_text' => $data[0]['generated_text']]);

        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json(['error' => 'Error al generar el texto'], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
