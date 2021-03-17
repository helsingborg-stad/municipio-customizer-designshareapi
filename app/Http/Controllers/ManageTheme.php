<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ManageTheme extends Controller
{
    private $storage = 'themes'; 

    /**
     * Get all files stored in themes folder
     *
     * @return Response
     */
    public function index() {

        $files = Storage::disk('local')->files($this->storage);

        if(is_array($files) && !empty($files)) {
            foreach($files as $file) {
                $content = (object) json_decode(Storage::get($file));

                $items[] = [
                    'id' => isset($content->id) ? $content->id : null,
                    'name' => isset($content->name) ? $content->name : null 
                ];
            }
            return Response::json($items, 200); 
        }

        return Response::json(array(
            'code'      =>  500,
            'message'   =>  "Could not find any themes." 
        ), 500); 
    }

    /**
     * Get a single theme definition
     *
     * @param string $id A varchar id
     * @return Response
     */
    public function single($id) {

        if(!Storage::disk('local')->has($this->storage . "/" . $id . ".json")) {
            return Response::json(array(
                'code'      =>  404,
                'message'   =>  "Theme does not exists." 
            ), 404); 
        }

        return Response::json(
            json_decode(
                Storage::get($this->storage . "/" . $id . ".json")
        ), 200); 
    }

    /**
     * Update a theme
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request) {

        //Validate client secret
        $clientSecret = $request->header('CLIENT-SITE-ID'); 
        if(strlen($clientSecret) != 32) {
            return Response::json(array(
                'code'      =>  400,
                'message'   =>  "Header 'CLIENT-SITE-ID' is not valid, please proide a string containing 32 chars." 
            ), 400); 
        }

        //Create a filename dependant on the client secret and app_secret
        $fileName = $this->createInternalId($clientSecret); 

        //Get file data
        $input = $request->only(['website', 'name', 'mods']);

        //Append local data
        $input['public']    = $this->isPublic($input['website']);
        $input['updated']   = date('Y-m-d H:i:s');
        $input['id']        = $fileName; 

        //Store file on disk
        Storage::disk('local')->put(
            $this->storage . '/' . $fileName . '.json', 
            json_encode($input),
            'private'
        );

        //Return response with data submitted
        return Response::json(array(
            'code'      =>  200,
            'message'   =>  'Record updated with data.',
            'data'      =>  $input 
        ), 200);
    }

    /**
     * Check if domain is accessible
     *
     * @return boolean
     */
    private function isPublic($url) {
        try {
            $client     = new \GuzzleHttp\Client(['verify' => false]);
            $response   = $client->get($url);

            if($response->getStatusCode() == 200) {
                return true;
            }
        } catch(Error $e) {
            return false; 
        }
        return false; 
    }

    /**
     * Create a secret key for internal identification
     *
     * @param string    $key   The client key
     * @return string   $key   Internal secret key
     */
    private function createInternalId($key) {

        //Create key
        if(getenv('APP_SECRET', true)) {
            return md5($key . getenv('APP_SECRET')); 
        }

        //Log error
        error_log("No private APP_SECRET enviroment variable defined. The system works without it, but should be considered insecure."); 

        //Create key
        return md5($key); 
    }
}
