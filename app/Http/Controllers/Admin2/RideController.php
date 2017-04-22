<?php 
namespace Caronae\Http\Controllers\Admin2;

use Backpack\Base\app\Http\Controllers\Controller as Controller;
use Caronae\Models\Ride;
use Caronae\Http\Requests\RankingRequest;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function index(Request $request)
    {
        $this->data['title'] = 'Caronas';
        return view('rides.index2', $this->data);
    }

    public function indexJson(RankingRequest $request)
    {
        return Ride::getInPeriodWithUserInfo($request->getDate('start'), $request->getDate('end'));
    }

    public function show(Ride $ride)
    {
        $this->data['title'] = 'Carona ' . $ride->id;
        return view('rides.show', $this->data, ['ride' => $ride]);
    }

}