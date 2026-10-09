<?php
/**
 * YouTube Transcript API - Versão 5.0 (Ultra Avançada)
 * Múltiplos métodos de extração incluindo técnicas anti-bot bypass
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('max_execution_time', 120);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log para debug
$debugLogs = [];
function debugLog($message, $data = null) {
    global $debugLogs;
    $debugLogs[] = ['msg' => $message, 'data' => $data, 'time' => microtime(true)];
}

/**
 * Classe principal para extrair transcrições
 */
class YouTubeTranscript {
    
    private $videoId;
    private $cookieFile;
    
    // User agents de navegadores reais
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];
    
    public function __construct($videoId) {
        $this->videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', $videoId);
        $this->cookieFile = sys_get_temp_dir() . '/yt_cookies_' . md5($this->videoId . time()) . '.txt';
    }
    
    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }
    
    /**
     * Faz requisição HTTP com cURL simulando navegador real
     */
    private function httpGet($url, $extraHeaders = [], $referer = 'https://www.youtube.com/') {
        $ch = curl_init();
        
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->userAgents[array_rand($this->userAgents)],
            CURLOPT_HTTPHEADER => array_merge($headers, $extraHeaders),
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_REFERER => $referer,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return ['error' => $error, 'code' => 0, 'body' => ''];
        }
        
        return ['body' => $response, 'code' => $httpCode, 'error' => null];
    }
    
    /**
     * Faz requisição POST
     */
    private function httpPost($url, $data, $extraHeaders = []) {
        $ch = curl_init();
        
        $headers = [
            'Accept: */*',
            'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Content-Type: application/json',
            'Origin: https://www.youtube.com',
            'Referer: https://www.youtube.com/',
            'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: same-origin',
            'Sec-Fetch-Site: same-origin',
            'X-Youtube-Client-Name: 1',
            'X-Youtube-Client-Version: 2.20231219.04.00',
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => is_string($data) ? $data : json_encode($data),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->userAgents[0],
            CURLOPT_HTTPHEADER => array_merge($headers, $extraHeaders),
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return ['body' => $response, 'code' => $httpCode, 'error' => $error];
    }
    
    /**
     * Obtém a transcrição usando múltiplos métodos
     */
    public function getTranscript($preferredLang = 'auto') {
        debugLog("=== INICIANDO EXTRAÇÃO v5.0 ===", $this->videoId);
        
        // MÉTODO 1: Página do vídeo com consent bypass
        debugLog("Tentando Método 1: Watch Page com Consent Bypass");
        $result = $this->method1_WatchPageWithConsent($preferredLang);
        if ($result && !isset($result['error'])) {
            debugLog("✅ Método 1 funcionou!");
            return $result;
        }
        debugLog("❌ Método 1 falhou", $result['error'] ?? 'unknown');
        
        // MÉTODO 2: YouTube Innertube API (API interna)
        debugLog("Tentando Método 2: Innertube API");
        $result = $this->method2_InnertubeAPI($preferredLang);
        if ($result && !isset($result['error'])) {
            debugLog("✅ Método 2 funcionou!");
            return $result;
        }
        debugLog("❌ Método 2 falhou", $result['error'] ?? 'unknown');
        
        // MÉTODO 3: YouTube nocookie/embed
        debugLog("Tentando Método 3: YouTube Embed");
        $result = $this->method3_EmbedPage($preferredLang);
        if ($result && !isset($result['error'])) {
            debugLog("✅ Método 3 funcionou!");
            return $result;
        }
        debugLog("❌ Método 3 falhou", $result['error'] ?? 'unknown');
        
        // MÉTODO 4: Timedtext API direta com variações
        debugLog("Tentando Método 4: Timedtext API");
        $result = $this->method4_TimedtextAPI($preferredLang);
        if ($result && !isset($result['error'])) {
            debugLog("✅ Método 4 funcionou!");
            return $result;
        }
        debugLog("❌ Método 4 falhou", $result['error'] ?? 'unknown');
        
        // MÉTODO 5: Terceiros (youtubetranscript.com style)
        debugLog("Tentando Método 5: Serviço de terceiros");
        $result = $this->method5_ThirdPartyService($preferredLang);
        if ($result && !isset($result['error'])) {
            debugLog("✅ Método 5 funcionou!");
            return $result;
        }
        debugLog("❌ Método 5 falhou", $result['error'] ?? 'unknown');
        
        return [
            'error' => 'Não foi possível obter a transcrição. O vídeo pode ter legendas desabilitadas ou restrições regionais.',
            'videoId' => $this->videoId,
            'debugLogs' => $GLOBALS['debugLogs']
        ];
    }
    
    /**
     * MÉTODO 1: Página do vídeo com bypass de consent
     */
    private function method1_WatchPageWithConsent($preferredLang) {
        // Primeiro, aceitar cookies de consent
        $consentUrl = "https://www.youtube.com/";
        $this->httpGet($consentUrl);
        
        // Agora buscar o vídeo
        $videoUrl = "https://www.youtube.com/watch?v={$this->videoId}&hl=pt&gl=BR&has_verified=1";
        $response = $this->httpGet($videoUrl);
        
        if ($response['error'] || $response['code'] !== 200) {
            return ['error' => 'Falha ao acessar página do vídeo: ' . ($response['error'] ?? $response['code'])];
        }
        
        $html = $response['body'];
        debugLog("HTML recebido", strlen($html) . " bytes");
        
        // Procurar captionTracks de várias formas
        $tracks = $this->extractCaptionTracksFromHtml($html);
        
        if (empty($tracks)) {
            debugLog("Nenhum track encontrado no HTML, tentando ytInitialPlayerResponse");
            
            // Tentar extrair ytInitialPlayerResponse
            $playerResponse = $this->extractPlayerResponse($html);
            if ($playerResponse) {
                $tracks = $this->extractTracksFromPlayerResponse($playerResponse);
            }
        }
        
        debugLog("Tracks encontrados", count($tracks));
        
        if (empty($tracks)) {
            return ['error' => 'Nenhuma legenda encontrada na página'];
        }
        
        // Selecionar melhor track
        $selectedTrack = $this->selectBestTrack($tracks, $preferredLang);
        if (!$selectedTrack) {
            return ['error' => 'Não foi possível selecionar uma legenda'];
        }
        
        debugLog("Track selecionada", $selectedTrack['languageCode']);
        
        return $this->downloadCaption($selectedTrack);
    }
    
    /**
     * MÉTODO 2: YouTube Innertube API
     */
    private function method2_InnertubeAPI($preferredLang) {
        $apiUrl = "https://www.youtube.com/youtubei/v1/player?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8&prettyPrint=false";
        
        $payload = [
            'context' => [
                'client' => [
                    'hl' => 'pt',
                    'gl' => 'BR',
                    'clientName' => 'WEB',
                    'clientVersion' => '2.20231219.04.00',
                    'originalUrl' => "https://www.youtube.com/watch?v={$this->videoId}",
                    'platform' => 'DESKTOP',
                ],
                'user' => [
                    'lockedSafetyMode' => false
                ],
                'request' => [
                    'useSsl' => true,
                    'internalExperimentFlags' => [],
                    'consistencyTokenJars' => []
                ]
            ],
            'videoId' => $this->videoId,
            'playbackContext' => [
                'contentPlaybackContext' => [
                    'vis' => 0,
                    'splay' => false,
                    'autoCaptionsDefaultOn' => false,
                    'autonavState' => 'STATE_NONE',
                    'html5Preference' => 'HTML5_PREF_WANTS',
                    'signatureTimestamp' => 19950
                ]
            ],
            'racyCheckOk' => false,
            'contentCheckOk' => false
        ];
        
        $response = $this->httpPost($apiUrl, $payload);
        
        if ($response['error'] || $response['code'] !== 200) {
            return ['error' => 'Innertube API falhou: ' . ($response['error'] ?? $response['code'])];
        }
        
        $data = json_decode($response['body'], true);
        if (!$data) {
            return ['error' => 'Resposta Innertube inválida'];
        }
        
        debugLog("Innertube response keys", array_keys($data));
        
        // Extrair captions
        $captions = $data['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? [];
        
        if (empty($captions)) {
            debugLog("Innertube: Nenhuma caption encontrada");
            return ['error' => 'Nenhuma legenda na resposta Innertube'];
        }
        
        debugLog("Innertube captions encontradas", count($captions));
        
        $tracks = [];
        foreach ($captions as $caption) {
            $tracks[] = [
                'baseUrl' => $caption['baseUrl'] ?? '',
                'languageCode' => $caption['languageCode'] ?? 'unknown',
                'name' => $caption['name']['simpleText'] ?? ($caption['name']['runs'][0]['text'] ?? 'Unknown'),
                'isAutoGenerated' => ($caption['kind'] ?? '') === 'asr' || strpos($caption['vssId'] ?? '', '.asr') !== false,
            ];
        }
        
        $selectedTrack = $this->selectBestTrack($tracks, $preferredLang);
        if (!$selectedTrack) {
            return ['error' => 'Não foi possível selecionar legenda Innertube'];
        }
        
        return $this->downloadCaption($selectedTrack);
    }
    
    /**
     * MÉTODO 3: Página embed
     */
    private function method3_EmbedPage($preferredLang) {
        $embedUrl = "https://www.youtube-nocookie.com/embed/{$this->videoId}?hl=pt";
        $response = $this->httpGet($embedUrl, [], 'https://www.google.com/');
        
        if ($response['error'] || $response['code'] !== 200) {
            return ['error' => 'Embed page falhou'];
        }
        
        $html = $response['body'];
        debugLog("Embed HTML recebido", strlen($html) . " bytes");
        
        $tracks = $this->extractCaptionTracksFromHtml($html);
        
        if (empty($tracks)) {
            return ['error' => 'Nenhuma legenda no embed'];
        }
        
        $selectedTrack = $this->selectBestTrack($tracks, $preferredLang);
        if (!$selectedTrack) {
            return ['error' => 'Não foi possível selecionar legenda embed'];
        }
        
        return $this->downloadCaption($selectedTrack);
    }
    
    /**
     * MÉTODO 4: API timedtext direta
     */
    private function method4_TimedtextAPI($preferredLang) {
        // Primeiro obter a lista de legendas disponíveis
        $listUrl = "https://video.google.com/timedtext?type=list&v={$this->videoId}";
        $response = $this->httpGet($listUrl);
        
        debugLog("Timedtext list response", ['code' => $response['code'], 'length' => strlen($response['body'] ?? '')]);
        
        $tracks = [];
        
        if ($response['code'] === 200 && !empty($response['body'])) {
            // Parse XML da lista
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($response['body']);
            
            if ($xml !== false) {
                foreach ($xml->track as $track) {
                    $langCode = (string) ($track['lang_code'] ?? '');
                    $name = (string) ($track['name'] ?? '');
                    $kind = (string) ($track['kind'] ?? '');
                    
                    if ($langCode) {
                        $tracks[] = [
                            'languageCode' => $langCode,
                            'name' => $name ?: $langCode,
                            'isAutoGenerated' => $kind === 'asr',
                            'kind' => $kind,
                        ];
                    }
                }
            }
        }
        
        debugLog("Timedtext tracks encontrados", count($tracks));
        
        if (empty($tracks)) {
            // Tentar idiomas comuns diretamente
            $langs = $preferredLang !== 'auto' ? [$preferredLang] : ['pt', 'pt-BR', 'en', 'en-US', 'es'];
            
            foreach ($langs as $lang) {
                $track = [
                    'languageCode' => $lang,
                    'name' => $lang,
                    'isAutoGenerated' => false,
                ];
                
                $result = $this->downloadTimedtextCaption($track);
                if ($result && !isset($result['error'])) {
                    return $result;
                }
                
                // Tentar ASR
                $track['isAutoGenerated'] = true;
                $result = $this->downloadTimedtextCaption($track);
                if ($result && !isset($result['error'])) {
                    return $result;
                }
            }
            
            return ['error' => 'Nenhuma legenda encontrada via timedtext'];
        }
        
        $selectedTrack = $this->selectBestTrack($tracks, $preferredLang);
        return $this->downloadTimedtextCaption($selectedTrack);
    }
    
    /**
     * MÉTODO 5: Serviço de terceiros
     */
    private function method5_ThirdPartyService($preferredLang) {
        // Usar downsub.com API
        $apiUrl = "https://downsub.com/api/v1/video/{$this->videoId}";
        
        $response = $this->httpGet($apiUrl, [
            'Accept: application/json',
        ], 'https://downsub.com/');
        
        debugLog("Downsub response", ['code' => $response['code'], 'length' => strlen($response['body'] ?? '')]);
        
        if ($response['code'] === 200 && !empty($response['body'])) {
            $data = json_decode($response['body'], true);
            
            if ($data && isset($data['subtitles'])) {
                $langs = $preferredLang !== 'auto' ? [$preferredLang, 'pt', 'en'] : ['pt', 'pt-BR', 'en', 'en-US'];
                
                foreach ($langs as $lang) {
                    foreach ($data['subtitles'] as $sub) {
                        if (stripos($sub['language'] ?? '', $lang) !== false || 
                            stripos($sub['languageCode'] ?? '', $lang) !== false) {
                            
                            $subUrl = $sub['url'] ?? '';
                            if ($subUrl) {
                                $subResponse = $this->httpGet($subUrl);
                                if ($subResponse['code'] === 200 && strlen($subResponse['body']) > 50) {
                                    return $this->parseSubtitleContent($subResponse['body'], [
                                        'languageCode' => $sub['languageCode'] ?? $lang,
                                        'name' => $sub['language'] ?? $lang,
                                        'isAutoGenerated' => false,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return ['error' => 'Serviço de terceiros não retornou legendas'];
    }
    
    /**
     * Extrai caption tracks do HTML
     */
    private function extractCaptionTracksFromHtml($html) {
        $tracks = [];
        
        // Padrões para encontrar captionTracks
        $patterns = [
            '/"captionTracks"\s*:\s*(\[[\s\S]*?\])\s*,\s*"/',
            '/captionTracks["\']?\s*:\s*(\[[\s\S]*?\])\s*,/',
            '/"captionTracks"\s*:\s*(\[.*?\])/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $jsonStr = $matches[1];
                
                // Limpar
                $jsonStr = preg_replace('/\\\\u([0-9a-fA-F]{4})/', '\\u$1', $jsonStr);
                $jsonStr = str_replace(['\\"', "\\'"], ['"', "'"], $jsonStr);
                
                // Tentar decode
                $data = json_decode($jsonStr, true);
                
                if ($data && is_array($data)) {
                    foreach ($data as $track) {
                        if (isset($track['baseUrl'])) {
                            $baseUrl = $track['baseUrl'];
                            $baseUrl = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($m) {
                                return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
                            }, $baseUrl);
                            $baseUrl = str_replace(['\\u0026', '\u0026'], '&', $baseUrl);
                            
                            $name = 'Unknown';
                            if (isset($track['name']['simpleText'])) {
                                $name = $track['name']['simpleText'];
                            } elseif (isset($track['name']['runs'][0]['text'])) {
                                $name = $track['name']['runs'][0]['text'];
                            }
                            
                            $tracks[] = [
                                'baseUrl' => $baseUrl,
                                'languageCode' => $track['languageCode'] ?? 'unknown',
                                'name' => $name,
                                'isAutoGenerated' => ($track['kind'] ?? '') === 'asr' || strpos($track['vssId'] ?? '', '.asr') !== false,
                            ];
                        }
                    }
                    
                    if (!empty($tracks)) {
                        debugLog("Tracks extraídos do HTML", count($tracks));
                        return $tracks;
                    }
                }
            }
        }
        
        return $tracks;
    }
    
    /**
     * Extrai ytInitialPlayerResponse
     */
    private function extractPlayerResponse($html) {
        $patterns = [
            '/ytInitialPlayerResponse\s*=\s*(\{.+?\})\s*;\s*(?:var|const|let|<\/script>)/',
            '/ytInitialPlayerResponse\s*=\s*(\{[\s\S]*?\})\s*;/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $json = $matches[1];
                
                // Limpar
                $json = preg_replace('/,\s*}/', '}', $json);
                $json = preg_replace('/,\s*]/', ']', $json);
                
                $data = @json_decode($json, true);
                if ($data && json_last_error() === JSON_ERROR_NONE) {
                    debugLog("PlayerResponse extraído com sucesso");
                    return $data;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extrai tracks do playerResponse
     */
    private function extractTracksFromPlayerResponse($playerResponse) {
        $tracks = [];
        
        $captions = $playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? [];
        
        foreach ($captions as $caption) {
            $baseUrl = $caption['baseUrl'] ?? '';
            $baseUrl = str_replace(['\\u0026', '\u0026'], '&', $baseUrl);
            
            $name = 'Unknown';
            if (isset($caption['name']['simpleText'])) {
                $name = $caption['name']['simpleText'];
            } elseif (isset($caption['name']['runs'][0]['text'])) {
                $name = $caption['name']['runs'][0]['text'];
            }
            
            $tracks[] = [
                'baseUrl' => $baseUrl,
                'languageCode' => $caption['languageCode'] ?? 'unknown',
                'name' => $name,
                'isAutoGenerated' => ($caption['kind'] ?? '') === 'asr' || strpos($caption['vssId'] ?? '', '.asr') !== false,
            ];
        }
        
        return $tracks;
    }
    
    /**
     * Seleciona melhor track
     */
    private function selectBestTrack($tracks, $preferredLang = 'auto') {
        if (empty($tracks)) return null;
        
        $preferred = $preferredLang !== 'auto' ? [$preferredLang] : ['pt', 'pt-BR', 'pt-PT'];
        $fallback = ['en', 'en-US', 'en-GB', 'es', 'es-ES', 'fr', 'de'];
        $allLangs = array_merge($preferred, $fallback);
        
        // Prioridade 1: Legendas manuais no idioma preferido
        foreach ($allLangs as $lang) {
            foreach ($tracks as $track) {
                $code = strtolower($track['languageCode']);
                if (!$track['isAutoGenerated'] && (strpos($code, strtolower($lang)) === 0 || $code === strtolower($lang))) {
                    return $track;
                }
            }
        }
        
        // Prioridade 2: Legendas automáticas no idioma preferido
        foreach ($allLangs as $lang) {
            foreach ($tracks as $track) {
                $code = strtolower($track['languageCode']);
                if (strpos($code, strtolower($lang)) === 0 || $code === strtolower($lang)) {
                    return $track;
                }
            }
        }
        
        // Prioridade 3: Qualquer legenda manual
        foreach ($tracks as $track) {
            if (!$track['isAutoGenerated']) {
                return $track;
            }
        }
        
        // Último: Qualquer legenda
        return $tracks[0];
    }
    
    /**
     * Baixa e processa legenda
     */
    private function downloadCaption($track) {
        $baseUrl = $track['baseUrl'] ?? '';
        
        if (empty($baseUrl)) {
            return ['error' => 'URL da legenda não disponível'];
        }
        
        // Limpar URL
        $baseUrl = html_entity_decode($baseUrl, ENT_QUOTES, 'UTF-8');
        $baseUrl = str_replace(['\\u0026', '\u0026', '&amp;'], '&', $baseUrl);
        
        debugLog("Baixando legenda", substr($baseUrl, 0, 100));
        
        // Tentar diferentes formatos
        $formats = ['json3', 'srv3', 'vtt', 'srv1', ''];
        
        foreach ($formats as $fmt) {
            $url = $baseUrl;
            
            // Remover formato existente e adicionar novo
            $url = preg_replace('/[&?]fmt=[^&]*/', '', $url);
            if ($fmt) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'fmt=' . $fmt;
            }
            
            debugLog("Tentando formato: $fmt", substr($url, 0, 80));
            
            $response = $this->httpGet($url, [], "https://www.youtube.com/watch?v={$this->videoId}");
            
            if ($response['code'] !== 200 || empty($response['body'])) {
                continue;
            }
            
            $content = $response['body'];
            debugLog("Resposta recebida", strlen($content) . " bytes");
            
            if (strlen($content) < 50) {
                continue;
            }
            
            $result = $this->parseSubtitleContent($content, $track);
            if ($result && !isset($result['error']) && strlen($result['transcript'] ?? '') > 10) {
                return $result;
            }
        }
        
        return ['error' => 'Não foi possível baixar/processar a legenda'];
    }
    
    /**
     * Baixa legenda via timedtext API
     */
    private function downloadTimedtextCaption($track) {
        $lang = $track['languageCode'];
        $kind = $track['isAutoGenerated'] ? '&kind=asr' : '';
        
        $formats = ['srv3', 'json3', 'vtt', ''];
        
        foreach ($formats as $fmt) {
            $fmtParam = $fmt ? "&fmt=$fmt" : '';
            $url = "https://www.youtube.com/api/timedtext?v={$this->videoId}&lang={$lang}{$kind}{$fmtParam}";
            
            $response = $this->httpGet($url);
            
            if ($response['code'] === 200 && strlen($response['body']) > 50) {
                $result = $this->parseSubtitleContent($response['body'], $track);
                if ($result && !isset($result['error'])) {
                    return $result;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Parse conteúdo da legenda (JSON, XML, VTT)
     */
    private function parseSubtitleContent($content, $track) {
        $content = trim($content);
        
        // Detectar tipo
        if ($content[0] === '{' || $content[0] === '[') {
            return $this->parseJson($content, $track);
        } elseif (strpos($content, 'WEBVTT') !== false) {
            return $this->parseVtt($content, $track);
        } else {
            return $this->parseXml($content, $track);
        }
    }
    
    /**
     * Parse JSON
     */
    private function parseJson($content, $track) {
        $data = json_decode($content, true);
        if (!$data) {
            return null;
        }
        
        $segments = [];
        
        if (isset($data['events'])) {
            foreach ($data['events'] as $event) {
                if (!isset($event['segs'])) continue;
                
                $text = '';
                foreach ($event['segs'] as $seg) {
                    $text .= $seg['utf8'] ?? '';
                }
                
                $text = trim($text);
                if (empty($text) || $text === "\n") continue;
                
                $segments[] = [
                    'start' => ($event['tStartMs'] ?? 0) / 1000,
                    'dur' => ($event['dDurationMs'] ?? 3000) / 1000,
                    'text' => $text,
                ];
            }
        }
        
        if (empty($segments)) {
            return null;
        }
        
        return $this->formatTranscript($segments, $track);
    }
    
    /**
     * Parse VTT
     */
    private function parseVtt($content, $track) {
        $segments = [];
        
        preg_match_all('/(\d{2}:\d{2}:\d{2}\.\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}\.\d{3})\s*\n(.*?)(?=\n\n|\n\d{2}:|$)/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $start = $this->vttTimeToSeconds($match[1]);
            $end = $this->vttTimeToSeconds($match[2]);
            $text = strip_tags(trim($match[3]));
            
            if (!empty($text)) {
                $segments[] = [
                    'start' => $start,
                    'dur' => $end - $start,
                    'text' => $text,
                ];
            }
        }
        
        if (empty($segments)) {
            return null;
        }
        
        return $this->formatTranscript($segments, $track);
    }
    
    /**
     * Parse XML
     */
    private function parseXml($content, $track) {
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $segments = [];
        
        // Tentar SimpleXML
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($content);
        
        if ($xml !== false) {
            foreach ($xml->text as $text) {
                $start = (float) ($text['start'] ?? 0);
                $dur = (float) ($text['dur'] ?? 3);
                $textContent = strip_tags((string) $text);
                $textContent = html_entity_decode($textContent, ENT_QUOTES, 'UTF-8');
                $textContent = trim($textContent);
                
                if (!empty($textContent)) {
                    $segments[] = [
                        'start' => $start,
                        'dur' => $dur,
                        'text' => $textContent,
                    ];
                }
            }
        }
        
        // Fallback: regex
        if (empty($segments)) {
            preg_match_all('/<text[^>]*start="([^"]*)"[^>]*(?:dur="([^"]*)")?[^>]*>(.*?)<\/text>/is', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $start = (float) ($match[1] ?? 0);
                $dur = (float) ($match[2] ?? 3);
                $text = html_entity_decode(strip_tags($match[3]), ENT_QUOTES, 'UTF-8');
                $text = trim($text);
                
                if (!empty($text)) {
                    $segments[] = [
                        'start' => $start,
                        'dur' => $dur,
                        'text' => $text,
                    ];
                }
            }
        }
        
        if (empty($segments)) {
            return null;
        }
        
        return $this->formatTranscript($segments, $track);
    }
    
    /**
     * VTT time to seconds
     */
    private function vttTimeToSeconds($time) {
        $parts = explode(':', $time);
        if (count($parts) === 3) {
            return (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (float)$parts[2];
        }
        return 0;
    }
    
    /**
     * Formata transcrição final
     */
    private function formatTranscript($segments, $track) {
        $transcript = '';
        $srt = '';
        $currentParagraph = '';
        $lastEnd = 0;
        
        foreach ($segments as $i => $seg) {
            // SRT
            $startTime = $this->secondsToSrt($seg['start']);
            $endTime = $this->secondsToSrt($seg['start'] + $seg['dur']);
            $srt .= ($i + 1) . "\n{$startTime} --> {$endTime}\n{$seg['text']}\n\n";
            
            // Transcrição
            $gap = $seg['start'] - $lastEnd;
            if ($gap > 2 && !empty($currentParagraph)) {
                $currentParagraph = trim($currentParagraph);
                $currentParagraph = ucfirst($currentParagraph);
                if (!preg_match('/[.!?]$/', $currentParagraph)) {
                    $currentParagraph .= '.';
                }
                $transcript .= $currentParagraph . "\n\n";
                $currentParagraph = '';
            }
            
            if (!empty($currentParagraph) && !preg_match('/\s$/', $currentParagraph)) {
                $currentParagraph .= ' ';
            }
            
            $currentParagraph .= $seg['text'];
            $lastEnd = $seg['start'] + $seg['dur'];
        }
        
        // Último parágrafo
        if (!empty($currentParagraph)) {
            $currentParagraph = trim($currentParagraph);
            $currentParagraph = ucfirst($currentParagraph);
            if (!preg_match('/[.!?]$/', $currentParagraph)) {
                $currentParagraph .= '.';
            }
            $transcript .= $currentParagraph;
        }
        
        // Limpeza
        $transcript = preg_replace('/\s+/', ' ', $transcript);
        $transcript = preg_replace('/\n\s+/', "\n", $transcript);
        $transcript = preg_replace('/\n{3,}/', "\n\n", $transcript);
        $transcript = trim($transcript);
        
        $langNames = [
            'pt' => 'Português', 'pt-br' => 'Português (Brasil)',
            'en' => 'English', 'en-us' => 'English (US)',
            'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch',
        ];
        
        $langCode = strtolower($track['languageCode']);
        $langName = $langNames[$langCode] ?? ($langNames[substr($langCode, 0, 2)] ?? $track['languageCode']);
        
        return [
            'transcript' => $transcript,
            'srt' => $srt,
            'language' => $langName,
            'languageCode' => $track['languageCode'],
            'isAutoGenerated' => $track['isAutoGenerated'] ?? false,
            'segmentsCount' => count($segments),
            'wordCount' => str_word_count($transcript),
        ];
    }
    
    /**
     * Seconds to SRT format
     */
    private function secondsToSrt($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $ms = round(($seconds - floor($seconds)) * 1000);
        
        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $ms);
    }
    
    /**
     * Teste de diagnóstico
     */
    public function test() {
        global $debugLogs;
        $debugLogs = [];
        
        $result = [
            'status' => 'ok',
            'videoId' => $this->videoId,
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => phpversion(),
            'curl_version' => curl_version()['version'] ?? 'unknown',
            'tests' => [],
        ];
        
        // Teste 1: Watch page
        debugLog("Teste 1: Watch Page");
        $response = $this->httpGet("https://www.youtube.com/watch?v={$this->videoId}");
        $result['tests']['watchPage'] = [
            'httpCode' => $response['code'],
            'size' => strlen($response['body'] ?? ''),
            'hasCaptionTracks' => strpos($response['body'] ?? '', 'captionTracks') !== false,
            'hasPlayerResponse' => strpos($response['body'] ?? '', 'ytInitialPlayerResponse') !== false,
        ];
        
        if ($response['body']) {
            $tracks = $this->extractCaptionTracksFromHtml($response['body']);
            $result['tests']['watchPage']['tracksFound'] = count($tracks);
            if (!empty($tracks)) {
                $result['tests']['watchPage']['tracks'] = array_map(function($t) {
                    return [
                        'lang' => $t['languageCode'],
                        'auto' => $t['isAutoGenerated'],
                        'hasUrl' => !empty($t['baseUrl']),
                    ];
                }, array_slice($tracks, 0, 5));
            }
        }
        
        // Teste 2: Innertube
        debugLog("Teste 2: Innertube API");
        $innertubeResult = $this->method2_InnertubeAPI('auto');
        $result['tests']['innertube'] = [
            'success' => !isset($innertubeResult['error']),
            'error' => $innertubeResult['error'] ?? null,
            'transcriptLength' => strlen($innertubeResult['transcript'] ?? ''),
        ];
        
        // Teste 3: Timedtext list
        debugLog("Teste 3: Timedtext List");
        $listResponse = $this->httpGet("https://video.google.com/timedtext?type=list&v={$this->videoId}");
        $result['tests']['timedtextList'] = [
            'httpCode' => $listResponse['code'],
            'size' => strlen($listResponse['body'] ?? ''),
            'content' => substr($listResponse['body'] ?? '', 0, 500),
        ];
        
        $result['debugLogs'] = $debugLogs;
        
        return $result;
    }
}

/**
 * Classe para extrair metadados, tags, visualizações, avatar e banner 16:9
 */
class YouTubeMetadata {
    private static $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

    private static function httpGet($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => self::$ua,
            CURLOPT_HTTPHEADER => ['Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    public static function extract($input) {
        $input = trim($input);
        $videoId = null;
        $channelTarget = null;
        $isChannel = false;

        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/))([a-zA-Z0-9_-]{11})/', $input, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
            $videoId = $input;
        } elseif (preg_match('/youtube\.com\/(?:@|channel\/|c\/|user\/)/', $input) || str_starts_with($input, '@')) {
            $isChannel = true;
            $channelTarget = str_starts_with($input, '@') ? 'https://www.youtube.com/' . $input : $input;
        }

        $data = [
            'type' => $videoId ? 'video' : ($isChannel ? 'channel' : 'unknown'),
            'videoId' => $videoId,
            'title' => '',
            'author' => '',
            'authorUrl' => '',
            'channelId' => '',
            'channelAvatar' => '',
            'channelBanner' => '',
            'viewCount' => 0,
            'viewCountFormatted' => '0',
            'publishDate' => '',
            'tags' => [],
            'thumbnails' => []
        ];

        if ($videoId) {
            $data['thumbnails'] = [
                'maxres' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
                'standard' => "https://img.youtube.com/vi/{$videoId}/sddefault.jpg",
                'high' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
                'medium' => "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg",
                'default' => "https://img.youtube.com/vi/{$videoId}/default.jpg",
            ];

            // 1. oEmbed
            $oeRaw = self::httpGet("https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json");
            if ($oeRaw && $oe = json_decode($oeRaw, true)) {
                $data['title'] = $oe['title'] ?? '';
                $data['author'] = $oe['author_name'] ?? '';
                $data['authorUrl'] = $oe['author_url'] ?? '';
            }

            // 2. Watch page
            $wHtml = self::httpGet("https://www.youtube.com/watch?v={$videoId}");
            if ($wHtml) {
                if (preg_match('/ytInitialPlayerResponse\s*=\s*({.+?});/', $wHtml, $pm)) {
                    $pData = json_decode($pm[1], true);
                    $vd = $pData['videoDetails'] ?? [];
                    if (!empty($vd['keywords'])) $data['tags'] = $vd['keywords'];
                    if (!empty($vd['viewCount'])) {
                        $data['viewCount'] = (int)$vd['viewCount'];
                        $data['viewCountFormatted'] = self::formatViews($data['viewCount']);
                    }
                    if (!empty($vd['channelId'])) $data['channelId'] = $vd['channelId'];
                    if (empty($data['title']) && !empty($vd['title'])) $data['title'] = $vd['title'];
                    if (empty($data['author']) && !empty($vd['author'])) $data['author'] = $vd['author'];
                    if (!empty($pData['microformat']['playerMicroformatRenderer']['publishDate'])) {
                        $data['publishDate'] = $pData['microformat']['playerMicroformatRenderer']['publishDate'];
                    }
                }

                if (empty($data['tags']) && preg_match('/<meta name="keywords" content="([^"]+)"/', $wHtml, $km)) {
                    $data['tags'] = array_values(array_filter(array_map('trim', explode(',', $km[1]))));
                }
            }

            $channelTarget = $data['channelId'] ? "https://www.youtube.com/channel/" . $data['channelId'] : $data['authorUrl'];
        }

        // 3. Channel page
        if ($channelTarget) {
            $cHtml = self::httpGet($channelTarget);
            if ($cHtml) {
                if ($isChannel && preg_match('/<meta property="og:title" content="([^"]+)"/', $cHtml, $tm)) {
                    $data['author'] = $tm[1];
                    $data['title'] = $tm[1];
                }

                if (preg_match_all('/https:\/\/yt3\.googleusercontent\.com\/[a-zA-Z0-9_-]+=[^"\'\s]+/', $cHtml, $allImgs)) {
                    foreach ($allImgs[0] as $imgUrl) {
                        if (str_contains($imgUrl, 'fcrop') || str_contains($imgUrl, 'w2560') || str_contains($imgUrl, 'w2120') || str_contains($imgUrl, 'w1060')) {
                            $data['channelBanner'] = preg_replace('/=w\d+.*$/', '=w2560-fcrop64=1,00005a57ffffa5a8-k-c0xffffffff-no-nd-rj', $imgUrl);
                            break;
                        }
                    }
                    foreach ($allImgs[0] as $imgUrl) {
                        if (str_contains($imgUrl, 's160') || str_contains($imgUrl, 's900') || str_contains($imgUrl, 's176') || str_contains($imgUrl, 's88')) {
                            $data['channelAvatar'] = preg_replace('/=s\d+.*$/', '=s900-c-k-c0x00ffffff-no-rj', $imgUrl);
                            break;
                        }
                    }
                }
            }
        }

        return $data;
    }

    private static function formatViews($views) {
        if ($views >= 1000000000) return round($views / 1000000000, 1) . 'B';
        if ($views >= 1000000) return round($views / 1000000, 1) . 'M';
        if ($views >= 1000) return round($views / 1000, 1) . 'K';
        return (string)$views;
    }
}

// ==================== ROTEAMENTO ====================

$action = $_GET['action'] ?? '';
$videoId = $_GET['videoId'] ?? '';
$url = $_GET['url'] ?? '';
$language = $_GET['lang'] ?? 'auto';

switch ($action) {
    case 'metadata':
    case 'info':
        $query = !empty($url) ? $url : $videoId;
        if (empty($query)) {
            echo json_encode(['error' => 'URL ou ID de vídeo/canal não fornecido']);
            exit;
        }
        $meta = YouTubeMetadata::extract($query);
        echo json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;

    case 'proxy':
        $targetUrl = $_GET['img'] ?? $_GET['url'] ?? '';
        if (empty($targetUrl) || (!str_starts_with($targetUrl, 'https://img.youtube.com/') && !str_starts_with($targetUrl, 'https://i.ytimg.com/') && !str_starts_with($targetUrl, 'https://yt3.googleusercontent.com/'))) {
            http_response_code(400);
            echo json_encode(['error' => 'URL inválida para proxy']);
            exit;
        }
        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $content = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
        curl_close($ch);
        header('Content-Type: ' . $contentType);
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=86400');
        echo $content;
        break;

    case 'summary':
        // Extrai resumo estruturado da transcrição
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        $text = $body['text'] ?? '';
        if (empty($text)) {
            echo json_encode(['error' => 'Texto da transcrição não fornecido']);
            exit;
        }
        // Processamento algorítmico de sumarização
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordFreq = [];
        $words = preg_split('/\s+/', mb_strtolower($text));
        $stopwords = ['que', 'não', 'para', 'com', 'uma', 'este', 'isso', 'esse', 'essa', 'como', 'mais', 'muito', 'você', 'eles', 'elas', 'tudo', 'onde', 'quando', 'the', 'and', 'that', 'this', 'with', 'from', 'have', 'your', 'about'];
        foreach ($words as $w) {
            $w = trim($w, ".,!?:;\"'()[]{}");
            if (mb_strlen($w) > 3 && !in_array($w, $stopwords)) {
                $wordFreq[$w] = ($wordFreq[$w] ?? 0) + 1;
            }
        }
        arsort($wordFreq);
        $topKeywords = array_slice(array_keys($wordFreq), 0, 8);
        
        // Seleciona frases com maior relevância
        $scored = [];
        foreach ($sentences as $i => $s) {
            $score = 0;
            $sLower = mb_strtolower($s);
            foreach ($topKeywords as $kw) {
                if (str_contains($sLower, $kw)) $score += 2;
            }
            if (mb_strlen($s) > 25 && mb_strlen($s) < 220) {
                $scored[] = ['sentence' => trim($s), 'score' => $score, 'order' => $i];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $topSentences = array_slice($scored, 0, 5);
        usort($topSentences, fn($a, $b) => $a['order'] <=> $b['order']);
        $bullets = array_map(fn($item) => $item['sentence'], $topSentences);
        
        echo json_encode([
            'keywords' => $topKeywords,
            'summary' => $bullets
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'transcript':
        $videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', $videoId);
        if (empty($videoId) || strlen($videoId) !== 11) {
            echo json_encode(['error' => 'ID de vídeo inválido']);
            exit;
        }
        
        $yt = new YouTubeTranscript($videoId);
        $result = $yt->getTranscript($language);
        
        if (isset($result['error'])) {
            $result['debugLogs'] = $GLOBALS['debugLogs'];
        }
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    case 'test':
        if (empty($videoId)) {
            $videoId = 'dQw4w9WgXcQ';
        }
        
        $yt = new YouTubeTranscript($videoId);
        echo json_encode($yt->test(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    default:
        echo json_encode([
            'name' => 'YouTube Tools API Pro',
            'version' => '6.0.0',
            'endpoints' => [
                'metadata' => '?action=metadata&url={url_or_id}',
                'transcript' => '?action=transcript&videoId={id}&lang={auto|pt|en}',
                'summary' => 'POST ?action=summary (body: {text: "..."})',
                'proxy' => '?action=proxy&img={url}'
            ],
            'php' => phpversion(),
            'curl' => function_exists('curl_init') ? 'available' : 'not available',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;
}
