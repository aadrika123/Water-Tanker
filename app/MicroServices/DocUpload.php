<?php

namespace App\MicroServices;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

/**
 * | Created On-13-12-2021 
 * | Created By-Anshu Kumar
 * | Created For the Document Upload MicroService
 */
class DocUpload
{
    /**
     * | Image Document Upload
     * | @param refImageName format Image Name like SAF-geotagging-id (Pass Your Ref Image Name Here)
     * | @param requested image (pass your request image here)
     * | @param relativePath Image Relative Path (pass your relative path of the image to be save here)
     * | @return imageName imagename to save (Final Image Name with time and extension)
     */
    public function upload($refImageName, $image, $relativePath,$isTimeAttached = true)
    {
        $extention = $image->getClientOriginalExtension();
        $imageName = ($isTimeAttached ? time() . '-' :""). $refImageName . '.' . $extention;
        $image->move($relativePath, $imageName);

        return $imageName;
    }


    /**
     * | New DMS Code
     */
    public function checkDoc($request)
    {
        try {
            // $contentType = (collect(($request->headers->all())['content-type'] ?? "")->first());
            $dmsUrl = Config::get('constants.DMS_URL');
            $file = $request->document;
            $filePath = $file->getPathname();
            $hashedFile = hash_file('sha256', $filePath);
            $filename = ($request->document)->getClientOriginalName();
            $api = "$dmsUrl/backend/document/upload";
            $transfer = [
                "file" => $request->document,
                "tags" => $filename,
                // "reference" => 425
            ];
            $returnData = Http::withHeaders([
                "x-digest"      => "$hashedFile",
                "token"         => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
                "folderPathId"  => 1
            ])->attach([
                [
                    'file',
                    file_get_contents($filePath),
                    $filename
                ]
            ])->post("$api", $transfer);
            if ($returnData->successful()) {
                return (json_decode($returnData->body(), true));
            }
            throw new Exception((json_decode($returnData->body(), true))["message"] ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


    public function severalDoc(Request $request)
    {
        if (!$request->metaData) {
            $request->merge(["metaData" => ["vsm1.1", 1.1, null, $request->getMethod(), null,]]);
        }
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $response = ($this->MultipartHandle($request));
            return responseMsgs(true, "", $response, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function MultipartHandle(Request $request)
    {
        $dmsUrl = Config::get('constants.DMS_URL');
        $data = [];
        $header = apache_request_headers();
        $header = collect($header)->merge(
            [
                "token"         => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
                "folderPathId"  => 1
            ]
        );
        $dotIndexes = $this->generateDotIndexes($_FILES);
        $url = "$dmsUrl/backend/document/upload";
        foreach ($dotIndexes as $val) {

            $patern = "/\.name/i";
            if (!preg_match($patern, $val)) {
                continue;
            }
            $file = $this->getArrayValueByDotNotation($request->file(), preg_replace($patern, "", $val));

            $filePath = $file->getPathname();
            $hashedFile = hash_file('sha256', $filePath);
            $filename = $file->getClientOriginalName();
            $header = collect($header)->merge(
                ["x-digest"      => "$hashedFile"]
            );
            $postData = [
                "file" => $file,
                "tags" => $filename,
                // "reference" => 425
            ];
            $response = Http::withHeaders(
                // $header->toArray()
                [
                    "x-digest"      => "$hashedFile",
                    "token"         => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
                    "folderPathId"  => 1
                ]

            );
            $response->attach(
                [
                    [
                        'file',
                        file_get_contents($filePath),
                        $filename
                    ]
                ]

            );
            $response = $response->post("$url", $postData);
            if ($response->successful()) {
                $response = (json_decode($response->body(), true));
            } else {
                $response = [false, json_decode($response->body(), true), ""];
            }
            $keys = explode('.', $val);
            $currentLevel = &$data;
            foreach ($keys as $index => $key) {
                $patern = "/name/i";
                if (preg_match($patern, $key)) {
                    continue;
                }
                if (!isset($currentLevel[$key])) {
                    $currentLevel[$key] = [];
                }
                $currentLevel = &$currentLevel[$key];
            }
            $currentLevel = $response;
        }
        return $data;
    }

    public function getArrayValueByDotNotation(array $array, string $key)
    {
        $keys = explode('.', $key);

        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                return null; // Key doesn't exist in the array
            }
        }

        return $array;
    }

    public function generateDotIndexes(array $array, $prefix = '', $result = [])
    {

        foreach ($array as $key => $value) {
            $newKey = $prefix . $key;
            if (is_array($value)) {
                $result = $this->generateDotIndexes($value, $newKey . '.', $result);
            } else {
                $result[] = $newKey;
            }
        }
        return $result;
    }

    /**
     * | This function is to get the document url from the DMS for multiple documents
     */
    public function getDocUrl($documents)
    {
        $dmsUrl = Config::get('constants.DMS_URL');
        $apiUrl = "$dmsUrl/backend/document/view-by-reference";
        $data = collect();

        foreach ($documents as $document) {
            $postData = [
                'referenceNo' => $document->reference_no,
            ];
            if ($document->reference_no) {
                $response = Http::withHeaders([
                    "token" => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
                ])->post($apiUrl, $postData);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $key['id'] =  $document->id;
                    $key['doc_code'] =  $document->doc_code??"";
                    $key['verify_status'] =  $document->verify_status??"";
                    $key['owner_name'] =  $document->owner_name??"";
                    $key['remarks'] =  $document->remarks??"";
                    $key['owner_dtl_id'] =  $document->owner_dtl_id ?? null;
                    $key['doc_path'] = $responseData['data']['fullPath'] ?? null;
                    $key['latitude'] = $document->latitude ?? null;
                    $key['longitude'] = $document->longitude ?? null;
                    $key['responseData'] = $responseData;
                    $data->push($key);
                }
            }
        }
        return $data;
    }

    /**
     * | This function is to get the document url from the DMS for single documents
     */
    public function getSingleDocUrl($document)
    {
        $dmsUrl = Config::get('constants.DMS_URL');
        
        $apiUrl = "$dmsUrl/backend/document/view-by-reference";
        $key = collect();

        if ($document) {
            $postData = [
                'referenceNo' => $document->reference_no,
            ];
            $response = Http::withHeaders([
                "token" => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
            ])->post($apiUrl, $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $key['id'] =  $document->id ?? null;
                $key['doc_id'] =  $document->doc_id ?? null;
                $key['doc_code'] =  $document->doc_code??"";
                $key['verify_status'] =  $document->verify_status??"";
                $key['owner_name'] =  $document->owner_name??"";
                $key['remarks'] =  $document->remarks ?? null;
                $key['doc_path'] = $responseData['data']['fullPath'] ?? "";
                $key['responseData'] = $responseData;
                // $data->push($key);
            }
        }
        return $key;
    }

    /**
     * | Get full document path from DMS by reference number
     * | For water tanker free booking document upload
     */
    public function getDocPathByReferenceNo($referenceNo)
    {
        $dmsUrl = Config::get('constants.DMS_URL');
        $apiUrl = "$dmsUrl/backend/document/view-by-reference";
        
        $response = Http::withHeaders([
            "token" => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
        ])->post($apiUrl, ['referenceNo' => $referenceNo]);

        if ($response->successful()) {
            $responseData = $response->json();
            return $responseData['data']['fullPath'] ?? null;
        }
        
        return null;
    }
}
