<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ManageTheme extends Controller
{
    private $storage = 'themes';
    private $staleTime = "-1 Month";

    /**
     * Get all files stored in themes folder
     *
     * @return Response
     */
    public function index() {

        $items = [];
        $files = Storage::disk('local')->files($this->storage);

        if(is_array($files) && !empty($files)) {
            foreach($files as $file) {
                $content = (object) json_decode(Storage::get($file));

                //Do not include "stale" files 
                if(isset($content->updated)) {
                    if($this->isStaleTheme($content->updated, $this->staleTime)) {
                        continue;
                    }
                }

                if (isset($content->public) && $content->public == true) {
                   $items[] = [
                        'id' => isset($content->id) ? $content->id : null,
                        'name' => isset($content->name) && isset($content->website) ? $content->name . " (" . parse_url($content->website, PHP_URL_HOST) . ")" : null
                    ];
                }
            }

        }

        // An empty shared-design collection is a valid state, especially on a
        // fresh persistent volume. Keep the response shape stable for clients.
        usort($items, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return Response::json($items, 200);
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

        //Get file contents & Append stale status
        $fileContents = json_decode(Storage::get($this->storage . "/" . $id . ".json")); 
        if(is_object($fileContents)) {
            $fileContents->isStale = $this->isStaleTheme($fileContents->updated, $this->staleTime);
        }

        return Response::json($fileContents, 200);
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
        $input = $request->only(['website', 'name', 'dbVersion', 'mods', 'css']);

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

        $hostname = parse_url($url, PHP_URL_HOST);

        if ((bool) in_array($hostname, ['localhost', '127.0.0.1'])) {
            return false; 
        }

        if ((bool) dns_get_record($hostname, DNS_A)) {
            return true;
        }

        if ((bool) dns_get_record($hostname, DNS_CNAME)) {
            return true;
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

    /**
     * Check if a file timestamp is older than a certain time period.
     *
     * @param string $updated A file timestamp according to the format y-m-d H:i:s
     * @param string $timeToCheckFor A strtotime compatible string (eg. -1 month)
     * @return bool Returns true if the file timestamp is older than the specified time period, otherwise false.
     */
    private function isStaleTheme(string $updated, string $timeToCheckFor): bool {
        // Convert the updated timestamp to a UNIX timestamp
        $updatedTimestamp = strtotime($updated);
        
        // Calculate the timestamp for the comparison
        $timeToCheckTimestamp = strtotime($timeToCheckFor, time());
        
        // Check if the updated timestamp is older than the time to check for
        return $updatedTimestamp < $timeToCheckTimestamp;
    } 
}
