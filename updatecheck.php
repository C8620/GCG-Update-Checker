<?php
    // Constant value area
    $ua_discord = 'Mozilla/5.0 (DiscordBot; C86-Academic/ManchesterUniversity)';
    date_default_timezone_set('UTC');

    // A constant value wait, used to eliminate possible time difference.
    usleep(250000);     // This value: 0.25s
    
    // Left bracket of For.
    $round = 0;
    config:
    // Following is a not-so-good implementation of allowing multiple channels to be checked within one run.
    // To be honest, the use of 'goto' command should be strictly forbidden. (or, shouldn't?)
    // Configurations listed below are extracted from 'envir.json' form CN BiliSP/CPS package.
    switch($round){
        case 0:
            // Display Settings
            $envir_nickname = 'CN Production Android';
            $hexcolour = '003E74';
            // Request URL and body
            $serverurl = 'https://l1-prod-bili-serverlist-sssj.bilibiligame.net/checkupdate';
            $data = array(
                'version' => '10136',
                'platform' => 'Android',
                'envir' => 'bili',
            );
            // Additional configs
            // Device: Samsung Electronics Galaxy Z Fold 2 5G (CHN/HK)
            $useragent = 'Mozilla/5.0 (Linux; S; Android 12; en-GB; SM-F9160 Build/U3FVAD; C86-AC/LHR) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36 GCG-Env/CN-ANDR';
            break;
        case 1:
            // Display Settings
            $envir_nickname = 'CN Production OEM';
            $hexcolour = '002147';
            // Request URL and body
            $serverurl = 'https://l1-prod-uo-serverlist-sssj.bilibiligame.net/checkupdate';
            $data = array(
                'version' => '10136',
                'platform' => 'Android',
                'envir' => 'bili_uo',
            );
            // Additional configs
            // Device: Samsung Electronics Galaxy Z Flip (CHN/HK)
            $useragent = 'Mozilla/5.0 (Linux; S; Android 12; en-GB; SM-F7000 Build/S6FVA3; C86-AC/PVG) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36 GCG-Env/CN-AOEM';
            break;
        case 2:
            // Display Settings
            $envir_nickname = 'EN Production';
            $hexcolour = '660099';
            // Request URL and body
            $serverurl = 'https://l11-prod-us-serverlist-sssj.bilibiligame.net/checkupdate';
            $data = array(
                'version' => '10007',
                'platform' => 'Android',
                'envir' => 'en',
            );
            // Additional configs
            // Device: Samsung Electronics Galaxy Fold 5G (Europe)
            $useragent = 'Mozilla/5.0 (Linux; S; Android 12; en-GB; SM-F907B Build/S6GVA1; C86-AC/JFK) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36 GCG/EN-PROD';
            break;
        default:
            exit();
    }
    
    // Construct JSON request body.
    $post_data = json_encode($data);
    
    // Prepare new cURL resource
    $remote_query = curl_init($serverurl);
    curl_setopt( $remote_query, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt( $remote_query, CURLOPT_USERAGENT, $useragent);
    curl_setopt( $remote_query, CURLOPT_POST, 1);
    curl_setopt( $remote_query, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt( $remote_query, CURLOPT_HEADER, 0);
    curl_setopt( $remote_query, CURLOPT_RETURNTRANSFER, 1);
    
    // Submit the POST request
    $result = curl_exec($remote_query);
    
    // Handle curl error
    if ($result === false) {
        // Throw exception if failed.
        die('Failed to fetch remote server version: ' . curl_error($remote_query));
    }
    // Decode server response, extract remote version.
    $result_decode = json_decode((string)$result, $associative = true);

    // SOMETIMES (~5%) request may return as HTTP 504 (shame on you!), save response
    // (to analyze if it is a rate-limiting effort) and abort checking process.
    if(!isset($result_decode['urllist']['buildversion'])){
        $file = fopen("exception.txt", "a") or die("Unable to open file!");
        fwrite($file, "+++++++++++++ ".date(DATE_ATOM, time())." Round $round: $envir_nickname\n");
        fwrite($file, $result."\n--------------------------------\n\n");
        fclose($file);
        goto config;
    }
    // Continue to decode.
    $curr_version = $result_decode['urllist']['buildversion'];
    // Close cURL session handle
    curl_close($remote_query);

    // Try to fetch the last version from filesystem.
    $filename = "lastversion.".$data['envir'].".txt";
    $file = fopen($filename, "r");
    if($file === false) {
        // In case of no previous version, use '0' as a default value.
        $last_version = '0';
    } else {
        // ...otherwise, read the latest available version.
        $filesize = filesize($filename);
        if($filesize>0){
            $last_version = fread($file, $filesize);
        }else{
            // If filesize is indeed 0, PHP would encounter a fatal error.
            // Use '0' as a default value.
            $last_version = '0';
        }
        fclose( $file );
    }

    // Is a differnet version?
    if((int)$curr_version!=(int)$last_version){
        // New version found! Update & Push to Discord.
        $file = fopen("lastversion.".$data['envir'].".txt", "w") or die("Unable to open file!");
        fwrite($file, $curr_version);
        fclose($file);

        $webhookurl = "https://discord.com/api/webhooks/";
        $timestamp = date("c", strtotime("now"));
        // Webhook body.
        $json_data = json_encode([
            // Text-to-speech
            "tts" => false,
            // Embeds Array
            "embeds" => [
                [
                    // Embed Title
                    "title" => "New version issued! ",
                    // Embed Type
                    "type" => "rich",
                    // Embed Description
                    "description" => "Environment: $envir_nickname\n Version change: $last_version -> $curr_version.",
                    // Timestamp of embed must be formatted as ISO8601
                    "timestamp" => $timestamp,
                    // Embed left border color in HEX
                    "color" => hexdec( $hexcolour ),
                    // Footer
                    "footer" => [
                        "text" => "C86AC Manchester"
                    ]
                ]
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        
        // Set webhook request.
        $ch = curl_init( $webhookurl );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt( $ch, CURLOPT_USERAGENT, $ua_discord);
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec( $ch );
        curl_close( $ch );
    }
    
    // Right bracket of For.
    $round += 1;
    goto config;
    // Hell knows why I have written this in this way.
?>
