<?php
/*
On Hangouts Chat - Incoming webhooks let you quickly define a one-off bot that injects messages into a room.

To set up and use a webhook is straightforward:

1) Define the incoming webhook in Hangouts Chat, provide a name and optionally an avatar for the bot.
2) Copy the system-generated URL and save it for your bot to use
3) Your bot can send messages to that URL, using the message format elements.

*/

function send_message_to_chat_room($chat_room_web_hook_url, $chat_message) {
	
	$url = $chat_room_web_hook_url;
	
	$chat_data = array('text'=> $chat_message);
	
	$content = json_encode($chat_data);	
	
	echo "\n Debug==> POST CONTENT: ".$content;
	
	$headers = array(
		"Content-type: application/json; charset=UTF-8;",
		"Content-Length: " . strlen($content),
		"Accept: application/json"
	);
	
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	curl_setopt($ch, CURLOPT_URL, $url);
	
	curl_setopt($ch, CURLOPT_POST, 1);
	
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$server_output = curl_exec($ch);
	
	//echo "\n server output: ". $server_output;
	
	if ($server_output == "OK") {
		
	} else {
		echo curl_error($ch);
	}
	
	curl_close ($ch);
	
}