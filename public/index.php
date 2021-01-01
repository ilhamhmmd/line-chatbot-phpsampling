<?php
require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;

$pass_signature = true;

// set LINE channel_access_token and channel_secret
$channel_access_token = "qDtcTT0sXm42bD2ePTMNRg2PkMVZpYCPaHL3q/W5HrVBJjyMzKYcdaKjj/4QsRSFYYKogRFK11v+3eL042YHukYDHl7qoeHGXKRVhkzXfXhlVWLmd+hI/xB8iKBEXhBEjl00IsVJXLjSfHJZ+ANVCwdB04t89/1O/w1cDnyilFU=";
$channel_secret = "c81450841c08c07dd1c93d15bbc3f0bb";

// inisiasi objek bot
$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);

$app = AppFactory::create();
$app->setBasePath("/public");

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello World!");
    return $response;
});

// buat route untuk webhook
$app->post('/webhook', function (Request $request, Response $response) use ($channel_secret, $bot, $httpClient, $pass_signature) {
    // get request body and line signature header
    $body = $request->getBody();
    $signature = $request->getHeaderLine('HTTP_X_LINE_SIGNATURE');

    // log body and signature
    file_put_contents('php://stderr', 'Body: ' . $body);

    if ($pass_signature === false) {
        // is LINE_SIGNATURE exists in request header?
        if (empty($signature)) {
            return $response->withStatus(400, 'Signature not set');
        }

        // is this request comes from LINE?
        if (!SignatureValidator::validateSignature($body, $channel_secret, $signature)) {
            return $response->withStatus(400, 'Invalid signature');
        }
    }

    // Kode Aplikasi Disini
    $data = json_decode($body, true);
    if (is_array($data['events'])) {
        foreach ($data['events'] as $event) {
            if ($event['type'] == 'message') {
                //reply message
                if ($event['message']['type'] == 'text') {
                    if (strtolower($event['message']['text']) == 'user id') {

                        $result = $bot->replyText($event['replyToken'], $event['source']['userId']);

                    } elseif (strtolower($event['message']['text']) == 'flex message') {

                        $flexTemplate = file_get_contents("../flex_message.json"); // template flex message
                        $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
                            'replyToken' => $event['replyToken'],
                            'messages'   => [
                                [
                                    'type'     => 'flex',
                                    'altText'  => 'Test Flex Message',
                                    'contents' => json_decode($flexTemplate)
                                ]
                            ],
                        ]);

                    } else {
                        // send same message as reply to user
                        // $event['message']['text'] merupakan text kiriman dari user
                        // $result = $bot->replyText($event['replyToken'], $event['message']['text']);

                        //Kirim pesan dengan stiker
                        $packageId = 1;
                        $stickerId = 121;                        
                        // $stickerMessageBuilder = new StickerMessageBuilder($packageId, $stickerId);

                        $resMultiMessage = [
                            'textMessageBuilder' => new TextMessageBuilder('ini pesan balasan pertama'),
                            'stickerMessageBuilder' => new StickerMessageBuilder($packageId, $stickerId)
                        ];

                        // Multiple Message
                        // $textMessageBuilder1 = new TextMessageBuilder('ini pesan balasan pertama');
                        // $textMessageBuilder2 = new TextMessageBuilder('ini pesan balasan kedua');

                        $multiMessageBuilder = new MultiMessageBuilder();
                        $multiMessageBuilder->add($resMultiMessage);

                        // or we can use replyMessage() instead to send reply message
                        // $textMessageBuilder = new TextMessageBuilder($event['message']['text']);
                        $result = $bot->replyMessage($event['replyToken'], $multiMessageBuilder);
                    }

                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                } //content api
                elseif (
                    $event['message']['type'] == 'image' or
                    $event['message']['type'] == 'video' or
                    $event['message']['type'] == 'audio' or
                    $event['message']['type'] == 'file'
                ) {
                    $contentURL = " https://example.herokuapp.com/public/content/" . $event['message']['id'];
                    $contentType = ucfirst($event['message']['type']);
                    $result = $bot->replyText($event['replyToken'],
                        $contentType . " yang Anda kirim bisa diakses dari link:\n " . $contentURL);

                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                } //group room
                elseif (
                    $event['source']['type'] == 'group' or
                    $event['source']['type'] == 'room'
                ) {
                    //message from group / room
                    if ($event['source']['userId']) {

                        $userId = $event['source']['userId'];
                        $getprofile = $bot->getProfile($userId);
                        $profile = $getprofile->getJSONDecodedBody();
                        $greetings = new TextMessageBuilder("Halo, " . $profile['displayName']);

                        $result = $bot->replyMessage($event['replyToken'], $greetings);
                        $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withStatus($result->getHTTPStatus());
                    }
                } else {
                    //message from single user
                    $result = $bot->replyText($event['replyToken'], $event['message']['text']);
                    $response->getBody()->write((string)$result->getJSONDecodedBody());
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                }
            }
        }
        return $response->withStatus(200, 'for Webhook!'); //buat ngasih response 200 ke pas verify webhook
    }
    return $response->withStatus(400, 'No event sent!');
});

$app->run();
