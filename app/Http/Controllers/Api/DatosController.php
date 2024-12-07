<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;


class DatosController extends Controller
{
    /**
     * Clasifica en una categoria el mensaje enviado atravez del microfono para enfocar la informacion
     * en una sola base de datos o funcion especifica con sus respectivos filtros
     */

    public function microfono(Request $request)
    {
        // Recibe el mensaje del request
        $mensaje = $request->input('mensaje');

        try {
            // Configura las categorias de clasificación
            $labels = ['fecha', 'segura', 'zona', 'clima', 'mapa','hospital'];

            $client = new Client();
            $response = $client->post('https://api-inference.huggingface.co/models/facebook/bart-large-mnli', [
                'headers' => [
                    'Authorization' => 'Bearer '.env('HUGGINGFACE_API_KEY'), // Reemplaza con tu API Key de Hugging Face
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $mensaje,
                    'parameters' => [
                        'candidate_labels' => $labels,
                    ],
                ],
            ]);
            $result = json_decode($response->getBody(), true);

            // Obtén la categoria con mayor relacion con el mensaje
            $label = $result['labels'][0];

            //return response()->json(['respuesta' => $label,'tipo'=>$label]);

            $respuesta = '' ;
            $tipo = '';
            $datos = '';
            // Retorna mensaje con fecha
            if ($label === 'fecha') {
                Carbon::setLocale('es');
                $respuesta = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY HH:mm');
                $tipo = 'fecha';
            } 
            //comprueba si la zona es segura
            if ($label === 'zona' || $label === 'segura') {
           
                $data = $this->zonasegura($request);
                if ($data->status() === 200) {
                    $respuestaData = $data->getData();
                    if ($respuestaData->respuesta === "Zona insegura") {
                        $respuesta = "Estás en una zona insegura";
                        $tipo = 'zona insegura';
                    } else {
                        $respuesta = "Estás en una zona segura";
                        $tipo = 'zona segura';
                    }
                } else {
                    $respuesta = "Error al obtener datos de la zona";
                    $tipo = 'desconocido';
                }
            }

            //comprueba el clima actual de la persona
            if ($label === 'clima') {
                $data = $this->obtenerClima($request);
                $respuestaData = $data->getData();
                $respuesta = $respuestaData->respuesta;
                $tipo = 'clima';
            } 
            if ($label === 'hospital') {
                $data = $this->ipscercana($request);
                $respuestaData = $data->getData();
                $datos = $respuestaData;
                $respuesta = $respuestaData->respuesta;
                $tipo = 'hospital';
            } 

            

            return response()->json(['respuesta' => $respuesta, 'tipo'=>$tipo, 'datos'=>$datos]);

        } catch (\Exception $e) {
            return response()->json(['respuesta' => 'Error en la clasificación de mensaje'], 500);
        }
    }

    /**
     * Usa base de datos de datos publicos compara todas las latitudes y longirudes con la del usuario
     * para determinar si esta cerca de una zona insegura y advertir
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
         $response = Http::get($apiUrl);
 
         if ($response->successful()) {
             // Filtra y procesa los datos de ubicación descartando los que no sean validos para retornar solo las coordenadas
             $ubicaciones = collect($response->json())
                 ->filter(function ($item) {
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
                     $latitudZona = (float) $ubicacion['latitud'];
                     $longitudZona = (float) $ubicacion['longitud'];
                     $latitudUsuario = (float) $latitudUsuario;
                     $longitudUsuario = (float) $longitudUsuario;
                 
                     if (!is_numeric($latitudZona) || !is_numeric($longitudZona) || !is_numeric($latitudUsuario) || !is_numeric($longitudUsuario)) {
                         return false; 
                     }
                 
                     $radioTierra = 6371;
                     $dLat = deg2rad($latitudZona - $latitudUsuario);
                     $dLon = deg2rad($longitudZona - $longitudUsuario);
                 
                     $a = sin($dLat / 2) * sin($dLat / 2) +
                          cos(deg2rad($latitudUsuario)) * cos(deg2rad($latitudZona)) *
                          sin($dLon / 2) * sin($dLon / 2);
                     $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                     $distancia = $radioTierra * $c;
                 
                     return $distancia <= $radioCercania;
                 });
                 
             if ($esZonaInsegura) {
                 return response()->json(['respuesta' => 'Zona insegura']);
             } else {
                 return response()->json(['respuesta' => 'Zona segura']);
             }
         } else {
             return response()->json([
                 'respuesta' => 'No se pudo obtener de la zona.'
             ], $response->status());
         }
    }

     
    /**
     * retorna lista de coordenas para crear una zona de calor con las zonas inseguras
     */
    public function mapa()
    {

        //api datos bucaramanga
        $apiUrl = 'https://www.datos.gov.co/api/id/75fz-q98y.json?$query=select%20*%20where%20ano%20=%20%272021%27';
        $response = Http::get($apiUrl);

        // Verifica si la solicitud fue exitosa
        if ($response->successful()) {
            $ubicaciones = collect($response->json())
            ->filter(function ($item) {
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
            return response()->json($ubicaciones);
        } else {
            return response()->json([
                'error' => 'No se pudo obtener los datos de la API externa.'
            ], $response->status());
        }
    }


    /**
     * retorna el clima actual en las cordenadas buscadas
     */
    public function obtenerClima($request)
    {
        return response()->json(['respuesta' => 'No se pudo obtener el clima.','tipo'=>'clima'],200);
        // URL de la API con el ID de la ciudad de Cúcuta y tu API Key
        $appid = env('OPENWEATHERMAP_API_KEY');
        $apiUrl = "https://api.openweathermap.org/data/2.5/weather?id=3685533&appid=".$appid."&units=metric&lang=es";
        
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


    /**
     * retorna el clima actual en las cordenadas actuales
     */
    public function obtenerClimaMiUbicacion(Request $request)
    {

        return response()->json(['respuesta' => 'No se pudo obtener el clima.','tipo'=>'clima'],200);
        // Coordenadas del usuario
        $latitudUsuario = $request->input('latitud');
        $longitudUsuario = $request->input('longitud');


        $appid = env('OPENWEATHERMAP_API_KEY');

        // URL de la API con el ID de la ciudad de Cúcuta y tu API Key
        $apiUrl = "https://api.openweathermap.org/data/2.5/weather?lat=".$latitudUsuario."&lon=".$longitudUsuario."&appid=".$appid."&units=metric&lang=es";
        
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




    /**
     * recorre un json de datos publicos y determina que sede esta más cercana cuando es solicitadas por el microfono
     */
    public function ipscercana(Request $request)
    {
        // Coordenadas del usuario
        $latitudUsuario = (float) $request->input('latitud');
        $longitudUsuario = (float) $request->input('longitud');


        // Check if the file exists
        $path = public_path('ips.json');
        if (!File::exists($path)) {
            return response()->json(['error' => 'No se pudo encontrar el archivo de IPs.'], 404);
        }

        // Parse the JSON file
        $ipsData = collect(json_decode(File::get($path), true));

        // Filter and map IP locations with valid latitude and longitude
        $ubicaciones = $ipsData->filter(function ($item) {
                return isset($item['latitud'], $item['longitud']) &&
                    $item['latitud'] !== 'xx.xxxx' &&
                    $item['longitud'] !== '-yy.yyyy';
            })
            ->map(function ($item) {
                return [
                    'nombre' => $item['nombre'] ?? null,
                    'latitud' => (float)str_replace(',', '.', $item['latitud']),
                    'longitud' => (float)str_replace(',', '.', $item['longitud']),
                ];
            })
            ->values();

        // Initialize variables to track the closest location
        $closestLocation = null;
        $minDistance = PHP_FLOAT_MAX;

        // Calculate the closest location
        foreach ($ubicaciones as $ubicacion) {
            $latitudZona = $ubicacion['latitud'];
            $longitudZona = $ubicacion['longitud'];

            $distancia = $this->calculateDistance($latitudUsuario, $longitudUsuario, $latitudZona, $longitudZona);

            // Update the closest location if a nearer one is found
            if ($distancia < $minDistance) {
                $minDistance = $distancia;
                $closestLocation = $ubicacion;
                $closestLocation['distancia'] = $distancia; // Optionally include the distance in the result
            }
        }

        if ($closestLocation) {
            return response()->json(['respuesta' => 'La ubicación más cercada es '.$closestLocation['nombre']??"No fue encontrada", 'ubicacion' => $closestLocation]);
        } else {
            return response()->json(['error' => 'No se encontró una ubicación cercana'], 404);
        }
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $radioTierra = 6371; 
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $radioTierra * $c;
    }

    /**
     * generar text coherente apartir de una frase
     */
    public function generateText($mensaje = "")
    {

        // Configura el cliente Guzzle
        $client = new Client();
        $apiUrl = "https://api-inference.huggingface.co/models/openai-community/gpt2";
        
        try {
            // Define los headers y el payload
            $headers = [
                'Authorization' => 'Bearer '.env('HUGGINGFACE_API_KEY'),
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


}
