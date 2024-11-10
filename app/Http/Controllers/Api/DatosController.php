<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DatosController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function zonasegura(Request $request)
    {
         // Coordenadas del usuario
         $latitudUsuario = $request->input('latitud');
         $longitudUsuario = $request->input('longitud');
 
         // Radio de distancia en kilómetros para definir cercanía (ejemplo: 0.5 km = 500 metros)
         $radioCercania = 0.5;
 
         // API de datos de Bucaramanga
         $apiUrl = 'https://www.datos.gov.co/api/id/75fz-q98y.json?$query=select%20*%20where%20ano%20=%20%272021%27';
 
         // Realiza la solicitud a la API externa
         $response = Http::get($apiUrl);
 
         // Verifica si la solicitud fue exitosa
         if ($response->successful()) {
             // Filtra y procesa los datos de ubicación
             $ubicaciones = collect($response->json())
                 ->filter(function ($item) {
                     // Verifica que el elemento tenga latitud y longitud válidas
                     return $item['latitud'] !== 'xx.xxxx' && $item['longitud'] !== '-yy.yyyy';
                 })
                 ->map(function ($item) {
                     return [
                         'latitud' => str_replace(',', '.', $item['latitud']),
                         'longitud' => str_replace(',', '.', $item['longitud']),
                     ];
                 })
                 ->values();
 
                 $esZonaInsegura = $ubicaciones->contains(function ($ubicacion) use ($latitudUsuario, $longitudUsuario, $radioCercania) {
                     // Convertir a float las coordenadas y verificar si son numéricas
                     $latitudZona = (float) $ubicacion['latitud'];
                     $longitudZona = (float) $ubicacion['longitud'];
                     $latitudUsuario = (float) $latitudUsuario;
                     $longitudUsuario = (float) $longitudUsuario;
                 
                     if (!is_numeric($latitudZona) || !is_numeric($longitudZona) || !is_numeric($latitudUsuario) || !is_numeric($longitudUsuario)) {
                         return false; // Ignora si alguna coordenada no es numérica
                     }
                 
                     // Conversión de grados a radianes y cálculo de distancia
                     $radioTierra = 6371; // Radio promedio de la Tierra en km
                     $dLat = deg2rad($latitudZona - $latitudUsuario);
                     $dLon = deg2rad($longitudZona - $longitudUsuario);
                 
                     $a = sin($dLat / 2) * sin($dLat / 2) +
                          cos(deg2rad($latitudUsuario)) * cos(deg2rad($latitudZona)) *
                          sin($dLon / 2) * sin($dLon / 2);
                     $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                     $distancia = $radioTierra * $c;
                 
                     // Comprueba si la distancia está dentro del radio de cercanía
                     return $distancia <= $radioCercania;
                 });
                 
             // Devuelve si es una zona insegura o segura
             if ($esZonaInsegura) {
                 return response()->json(['respuesta' => 'Zona insegura']);
             } else {
                 return response()->json(['respuesta' => 'Zona segura']);
             }
         } else {
             // En caso de error, devuelve un mensaje de error
             return response()->json([
                 'respuesta' => 'No se pudo obtener de la zona.'
             ], $response->status());
         }
    }

     
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


    public function obtenerClima($request)
    {
        // URL de la API con el ID de la ciudad de Cúcuta y tu API Key
        $apiUrl = "https://api.openweathermap.org/data/2.5/weather?id=3685533&appid=8fb651e5cdd8d9f304e94d53579e5f29&units=metric&lang=es";
        
        // Realiza la solicitud a la API
        $response = Http::get($apiUrl);
        
        // Verifica si la solicitud fue exitosa
        if ($response->successful()) {
            $data = $response->json();
            
            // Obtiene las condiciones climáticas y las concatena en una cadena
            $condiciones = collect($data['weather'])->map(function ($condicion) {
                return $condicion['description'];
            })->implode(', ');

            // Obtiene otros datos importantes como temperatura, humedad y visibilidad
            $temperatura = $data['main']['temp'];
            $sensacion = $data['main']['feels_like'];
            $humedad = $data['main']['humidity'];
            $visibilidad = $data['visibility'] / 1000; // convierte a km

            // Construye el mensaje de clima
            $mensaje = "Clima en Cúcuta: {$condiciones}. Temperatura actual: {$temperatura}°C, sensación térmica: {$sensacion}°C. Humedad: {$humedad}%. Visibilidad: {$visibilidad} km.";

            return response()->json(['respuesta' => $mensaje,'tipo'=>'clima']);
        } else {
            // En caso de error en la solicitud
            return response()->json(['respuesta' => 'No se pudo obtener el clima.','tipo'=>'clima'], $response->status());
        }
    }


    public function obtenerClimaMiUbicacion(Request $request)
    {

        // Coordenadas del usuario
        $latitudUsuario = $request->input('latitud');
        $longitudUsuario = $request->input('longitud');



        // URL de la API con el ID de la ciudad de Cúcuta y tu API Key
        $apiUrl = "https://api.openweathermap.org/data/2.5/weather?lat=".$latitudUsuario."&lon=".$longitudUsuario."&appid=8fb651e5cdd8d9f304e94d53579e5f29&units=metric&lang=es";
        
        // Realiza la solicitud a la API
        $response = Http::get($apiUrl);
        
        // Verifica si la solicitud fue exitosa
        if ($response->successful()) {
            $data = $response->json();
            
            // Obtiene las condiciones climáticas y las concatena en una cadena
            $condiciones = collect($data['weather'])->map(function ($condicion) {
                return $condicion['description'];
            })->implode(', ');

            // Obtiene otros datos importantes como temperatura, humedad y visibilidad
            $temperatura = $data['main']['temp'];
            $sensacion = $data['main']['feels_like'];
            $humedad = $data['main']['humidity'];
            $visibilidad = $data['visibility'] / 1000; // convierte a km

            // Construye el mensaje de clima
            $mensaje = "El cliema actual es {$condiciones}.";

            return response()->json(['respuesta' => $mensaje,'tipo'=>'clima']);
        } else {
            // En caso de error en la solicitud
            return response()->json(['respuesta' => 'No se pudo obtener el clima.','tipo'=>'clima'], $response->status());
        }
    }


    public function microfono(Request $request)
    {
        // Recibe el mensaje del request
        $mensaje = $request->input('mensaje');

        Log::info($request->all());

        try {
            // Configura las etiquetas de clasificación
            $labels = ['fecha', 'segura', 'zona', 'clima'];

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
            Log::info($label);
            $respuesta =$label ;
            $tipo = '';

            if ($label === 'fecha') {
                Carbon::setLocale('es');
                $respuesta = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY HH:mm');
                $tipo = 'fecha';
            } 

            if ($label === 'zona' || $label === 'segura') {
                // Llama a la función zonasegura
                $data = $this->zonasegura($request);
                
                // Verifica si la llamada fue exitosa
                if ($data->status() === 200) {
                    // Obtén la respuesta JSON
                    $respuestaData = $data->getData();
                    
                    // Verifica el valor de la respuesta
                    if ($respuestaData->respuesta === "Zona insegura") {
                        $respuesta = "Estás en una zona insegura";
                        $tipo = 'zona insegura';
                    } else {
                        $respuesta = "Estás en una zona segura";
                        $tipo = 'zona segura';
                    }
                } else {
                    // Manejo de error en la API
                    $respuesta = "Error al obtener datos de la zona";
                    $tipo = 'desconocido';
                }
            }

            if ($label === 'clima') {
                $data = $this->obtenerClima($request);
                $respuestaData = $data->getData();
                $respuesta = $respuestaData->respuesta;
                $tipo = 'clima';
            } 

            // Devuelve el número en la respuesta
            return response()->json(['respuesta' => $respuesta,'tipo'=>$tipo]);

        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json(['respuesta' => 'Error en la clasificación de mensaje'], 500);
        }
    }


  

    public function generateText($mensaje = "")
    {

        // Configura el cliente Guzzle
        $client = new Client();
        $apiUrl = "https://api-inference.huggingface.co/models/openai-community/gpt2";
        
        try {
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

            // Procesa la respuesta de la API
            $data = json_decode($response->getBody(), true);

            // Retorna el texto generado en JSON
            return $data[0]['generated_text'];

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
