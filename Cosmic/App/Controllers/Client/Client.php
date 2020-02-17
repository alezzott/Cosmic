<?php
namespace App\Controllers\Client;

use App\Core;
use App\Config;
use App\Token;

use App\Models\Api;
use App\Models\Ban;
use App\Models\Player;
use App\Models\Room;

use Core\Locale;
use Core\View;

use Library\HotelApi;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use stdClass;

class Client
{
    private $data;

    public function client()
    {
        $this->data = new stdClass();
    
        $reader = new Reader(__DIR__. Config::vpnLocation);

        try {
            $record = $reader->asn(request()->getIp());
        } catch (AddressNotFoundException $e) {
        } catch (InvalidDatabaseException $e) {

        }

        $asn = Ban::getNetworkBanByAsn($record->autonomousSystemNumber);

        if ($asn) {
            View::renderTemplate('Client/vpn.html', ['asn' => $asn->asn, 'type' => 'vpn']);
            exit;
        }
   

        $OS = substr($_SERVER['HTTP_USER_AGENT'], -2);
        if (strpos($_SERVER['HTTP_USER_AGENT'], "Puffin") !== false && ($OS == "WD" || $OS == "LD" || $OS == "MD")) {
            View::renderTemplate('Client/vpn.html', ['type' => 'puffin']);
            exit;
        }

        $user = Player::getDataById(request()->player->id);
      
        $this->data->shuttle_token = bin2hex(openssl_random_pseudo_bytes(48));
        $this->data->auth_ticket = Token::authTicket($user->id);
        $this->data->unique_id = sha1($user->id . '-' . time());

        Player::update($user->id, ["auth_ticket" => $this->data->auth_ticket, "shuttle_token" => $this->data->shuttle_token]);
      
        if($user->getMembership()) {
            HotelApi::execute('setrank', ['user_id' => $user->id, 'rank' => $user->getMembership()->old_rank]);
            $user->deleteMembership();
        }

        View::renderTemplate('Client/client.html', [
            'title' => Locale::get('core/title/hotel'),
            'data'  => $this->data
        ]);
    }

    public function hotel()
    {
        View::renderTemplate('base.html', [
            'title' => Locale::get('core/title/hotel'),
            'page'  => 'home'
        ]);
    }

    public function count()
    {
        echo \App\Models\Core::getOnlineCount();
        exit;
    }
}