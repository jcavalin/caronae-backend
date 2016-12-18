<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

use Caronae\Models\User;
use Caronae\Models\Ride;
use Caronae\Services\PushNotificationService;

class RideTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $headers;
    protected $push;

    /**
    * @before
    */
    public function cleanDatabase()
    {
        DB::table('ride_user')->delete();
        DB::table('users')->delete();
        DB::table('rides')->delete();
    }

    /**
    * @before
    */
    public function createFakeUserHeaders()
    {
        $this->user = factory(User::class)->create()->fresh();
        $this->headers = ['token' => $this->user->token];
    }

    /**
    * @before
    */
    public function mockPushNotificationService()
    {
        $this->push = Mockery::mock(PushNotificationService::class);
        App::instance(PushNotificationService::class, $this->push);
    }


    public function testGetAll()
    {
        $user = $this->user;
        $rideIds = [];
        $rides = factory(Ride::class, 'next', 2)->create()->each(function($ride) use ($user, &$rideIds) {
            $ride->users()->attach($user, ['status' => 'driver']);
            $rideIds[] = $ride->id;
        });

        $rideOld = factory(Ride::class)->create(['date' => '1990-01-01 00:00:00']);
        $rideOld->users()->attach($user, ['status' => 'driver']);

        $rides = Ride::findMany($rideIds);
        foreach ($rides as $ride) {
            $ride->driver = $user->toArray();
        }

        $response = $this->json('GET', 'ride/all', [], $this->headers);
        $response->assertResponseOk();

        $driverArray = [
            'id' => $user->id,
            'name' => $user->name,
            'profile' => $user->profile,
            'course' => $user->course,
            'phone_number' => $user->phone_number,
            'email' => $user->email,
            'car_owner' => $user->car_owner,
            'car_model' => $user->car_model,
            'car_color' => $user->car_color,
            'car_plate' => $user->car_plate,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            'location' => $user->location,
            'face_id' => $user->face_id,
            'profile_pic_url' => $user->profile_pic_url
        ];

        $response->seeJsonEquals([
            [
                'id' => $rides[0]->id,
                'myzone' => $rides[0]->myzone,
                'neighborhood' => $rides[0]->neighborhood,
                'going' => $rides[0]->going,
                'place' => $rides[0]->place,
                'route' => $rides[0]->route,
                'routine_id' => $rides[0]->routine_id,
                'hub' => $rides[0]->hub,
                'slots' => $rides[0]->slots,
                'mydate' => $rides[0]->date->format('Y-m-d'),
                'mytime' => $rides[0]->date->format('H:i:s'),
                'description' => $rides[0]->description,
                'week_days' => $rides[0]->week_days,
                'repeats_until' => $rides[0]->repeats_until,
                'driver' => $driverArray
            ],
            [
                'id' => $rides[1]->id,
                'myzone' => $rides[1]->myzone,
                'neighborhood' => $rides[1]->neighborhood,
                'going' => $rides[1]->going,
                'place' => $rides[1]->place,
                'route' => $rides[1]->route,
                'routine_id' => $rides[1]->routine_id,
                'hub' => $rides[1]->hub,
                'slots' => $rides[1]->slots,
                'mydate' => $rides[1]->date->format('Y-m-d'),
                'mytime' => $rides[1]->date->format('H:i:s'),
                'description' => $rides[1]->description,
                'week_days' => $rides[1]->week_days,
                'repeats_until' => $rides[1]->repeats_until,
                'driver' => $driverArray
            ]
        ]);
    }

    public function testSearch()
    {
        // TODO: test search with zone, search with neighborhood, search with/without center
    }

    public function testValidateValidRide()
    {
        $ride = factory(Ride::class)->create(['date' => '2016-12-18 16:00:00', 'going' => false]);
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $parameters = [
            'date' => '18/12/2016',
            'time' => '16:00:00',
            'going' => 1
        ];
        $response = $this->json('GET', 'ride/validateDuplicate', $parameters, $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'valid' => true,
            'status' => 'valid',
            'message' => 'No conflicting rides were found close to the specified date.'
        ]);
    }

    public function testValidatePossibleDuplicate()
    {
        $ride = factory(Ride::class)->create(['date' => '2016-12-18 10:00:00', 'going' => true]);
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $parameters = [
            'date' => '18/12/2016',
            'time' => '16:00:00',
            'going' => 1
        ];
        $response = $this->json('GET', 'ride/validateDuplicate', $parameters, $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'valid' => false,
            'status' => 'possible_duplicate',
            'message' => 'The user has already offered a ride too close to the specified date.'
        ]);
    }

    public function testValidateDuplicate()
    {
        $ride = factory(Ride::class)->create(['date' => '2016-12-18 16:20:00', 'going' => true]);
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $parameters = [
            'date' => '18/12/2016',
            'time' => '16:00:00',
            'going' => 1
        ];
        $response = $this->json('GET', 'ride/validateDuplicate', $parameters, $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'valid' => false,
            'status' => 'duplicate',
            'message' => 'The user has already offered a ride on the specified date.'
        ]);
    }

    public function testCreateWithoutRoutine()
    {
        $request = [
            'myzone' => 'Norte',
            'neighborhood' => 'Jardim Guanabara',
            'place' => 'Praia da bica',
            'route' => 'Linha Vermelha',
            'mydate' => '20/10/2016',
            'mytime' => '10:00:00',
            'week_days' => NULL,
            'repeats_until' => NULL,
            'slots' => '4',
            'hub' => 'A',
            'description' => 'Lorem ipsum dolor',
            'going' => false
        ];

        $response = $this->json('POST', 'ride', $request, $this->headers);
        $response->assertResponseOk();

        $response->seeJsonContains([
            'myzone' => 'Norte',
            'neighborhood' => 'Jardim Guanabara',
            'place' => 'Praia da bica',
            'route' => 'Linha Vermelha',
            'mydate' => '2016-10-20',
            'mytime' => '10:00:00',
            'slots' => '4',
            'hub' => 'A',
            'description' => 'Lorem ipsum dolor',
            'going' => false
        ]);

        $response->seeJsonStructure([
            '*' => ['id']
        ]);
    }

    public function testCreateWithRoutine()
    {
        $request = [
            'myzone' => 'Norte',
            'neighborhood' => 'Jardim Guanabara',
            'place' => 'Praia da bica',
            'route' => 'Linha Vermelha',
            'mydate' => '24/10/2016',
            'mytime' => '16:40:00',
            'week_days' => '2,4', // tuesday, thursday
            'repeats_until' => '01/11/2016',
            'slots' => '4',
            'hub' => 'A',
            'description' => 'Lorem ipsum dolor',
            'going' => true
        ];

        $response = $this->json('POST', 'ride', $request, $this->headers);
        $response->assertResponseOk();

        $jsonContent = json_decode($this->response->getContent());
        $this->assertEquals(4, count($jsonContent), "Should create exactly 4 rides.");

        $response->seeJsonContains([
            'myzone' => 'Norte',
            'neighborhood' => 'Jardim Guanabara',
            'place' => 'Praia da bica',
            'route' => 'Linha Vermelha',
            'mydate' => '2016-10-24',
            'mytime' => '16:40:00',
            'slots' => '4',
            'hub' => 'A',
            'description' => 'Lorem ipsum dolor',
            'going' => true
        ]);
        $response->seeJsonContains([
            'myzone' => 'Norte',
            'neighborhood' => 'Jardim Guanabara',
            'place' => 'Praia da bica',
            'route' => 'Linha Vermelha',
            'mydate' => '2016-10-25',
            'mytime' => '16:40:00',
            'slots' => '4',
            'hub' => 'A',
            'description' => 'Lorem ipsum dolor',
            'going' => true
        ]);
        $response->seeJsonContains([
            'myzone' => 'Norte',
            'neighborhood' => 'Jardim Guanabara',
            'place' => 'Praia da bica',
            'route' => 'Linha Vermelha',
            'mydate' => '2016-10-27',
            'mytime' => '16:40:00',
            'slots' => '4',
            'hub' => 'A',
            'description' => 'Lorem ipsum dolor',
            'going' => true
        ]);
        $response->seeJsonContains([
            'myzone' => 'Norte',
            'neighborhood' => 'Jardim Guanabara',
            'place' => 'Praia da bica',
            'route' => 'Linha Vermelha',
            'mydate' => '2016-11-01',
            'mytime' => '16:40:00',
            'slots' => '4',
            'hub' => 'A',
            'description' => 'Lorem ipsum dolor',
            'going' => true
        ]);

        $response->seeJsonStructure([
            '*' => ['id', 'routine_id', 'week_days']
        ]);
    }

    public function testDelete()
    {
        $ride = factory(Ride::class, 'next')->create();
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $response = $this->json('DELETE', 'ride/' . $ride->id, [], $this->headers);
        $response->assertResponseOk();
    }

    public function testDeleteAllFromRoutine()
    {
        $ride = factory(Ride::class, 'next')->create();
        $ride->users()->attach($this->user, ['status' => 'driver']);
        $ride->routine_id = $ride->id;
        $ride->save();

        $response = $this->json('DELETE', 'ride/allFromRoutine/' . $ride->id, [], $this->headers);
        $response->assertResponseOk();
    }

    public function testJoin()
    {
        $this->push->shouldReceive('sendNotificationToUser')->once()->andReturn();

        $ride = factory(Ride::class, 'next')->create();
        $user = factory(User::class)->create();
        $ride->users()->attach($user, ['status' => 'driver']);

        $request = [
            'rideId' => $ride->id
        ];

        $response = $this->json('POST', 'ride/requestJoin', $request, $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'message' => 'Request sent.'
        ]);
    }

    public function testGetRequesters()
    {
        $ride = factory(Ride::class, 'next')->create();
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $user = factory(User::class)->create();
        $ride->users()->attach($user, ['status' => 'pending']);

        $ride->users()->attach(factory(User::class)->create(), ['status' => 'accepted']);
        $ride->users()->attach(factory(User::class)->create(), ['status' => 'rejected']);

        $response = $this->json('GET', 'ride/getRequesters/' . $ride->id, [], $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            [
                'id' => $user->id,
                'name' => $user->name,
                'profile' => $user->profile,
                'course' => $user->course,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'car_owner' => $user->car_owner,
                'car_model' => $user->car_model,
                'car_color' => $user->car_color,
                'car_plate' => $user->car_plate,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'location' => $user->location,
                'face_id' => $user->face_id,
                'profile_pic_url' => $user->profile_pic_url
            ]
        ]);
    }

    public function testAnswerJoinRequest()
    {
        $this->push->shouldReceive('sendNotificationToUser')->once()->andReturn();

        $ride = factory(Ride::class, 'next')->create();
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $user2 = factory(User::class)->create();
        $ride->users()->attach($user2, ['status' => 'pending']);

        $request = [
            'rideId' => $ride->id,
            'userId' => $user2->id,
            'accepted' => true
        ];

        $response = $this->json('POST', 'ride/answerJoinRequest', $request, $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'message' => 'Request answered.'
        ]);
    }

    public function testLeave()
    {
        $this->push->shouldReceive('sendNotificationToUser')->once()->andReturn();

        $ride = factory(Ride::class, 'next')->create();
        $driver = factory(User::class)->create();
        $ride->users()->attach($driver, ['status' => 'driver']);

        $ride->users()->attach($this->user, ['status' => 'accepted']);

        $request = [
            'rideId' => $ride->id
        ];

        $response = $this->json('POST', 'ride/leaveRide', $request, $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'message' => 'Left ride.'
        ]);
    }

    public function testFinish()
    {
        $ride = factory(Ride::class, 'next')->create();
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $request = [
            'rideId' => $ride->id
        ];

        $response = $this->json('POST', 'ride/finishRide', $request, $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'message' => 'Ride finished.'
        ]);
    }

    public function testSaveFeedback()
    {
        // TODO
    }

    public function testGetActiveRides()
    {
        // TODO
    }

    public function testGetHistory()
    {
        $user2 = factory(User::class)->create()->fresh();

        $ride1 = factory(Ride::class)->create(['done' => true]);
        $ride2 = factory(Ride::class)->create(['done' => true]);
        $ride3 = factory(Ride::class)->create(['done' => false]);
        $ride4 = factory(Ride::class, 'next')->create();
        $ride5 = factory(Ride::class)->create(['done' => true]);
        $ride6 = factory(Ride::class)->create(['done' => true]);

        $ride1->users()->attach($this->user, ['status' => 'driver']);
        $ride2->users()->attach($this->user, ['status' => 'accepted']);
        $ride2->users()->attach($user2, ['status' => 'driver']);
        $ride3->users()->attach($this->user, ['status' => 'driver']);
        $ride4->users()->attach($this->user, ['status' => 'accepted']);
        $ride5->users()->attach($user2, ['status' => 'driver']);
        $ride6->users()->attach($this->user, ['status' => 'rejected']);

        $response = $this->json('GET', 'ride/getRidesHistory', [], $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            [
                'id' => $ride1->id,
                'myzone' => $ride1->myzone,
                'neighborhood' => $ride1->neighborhood,
                'going' => $ride1->going,
                'place' => $ride1->place,
                'route' => $ride1->route,
                'routine_id' => $ride1->routine_id,
                'hub' => $ride1->hub,
                'slots' => $ride1->slots,
                'mytime' => $ride1->date->format('H:i:s'),
                'mydate' => $ride1->date->format('Y-m-d'),
                'description' => $ride1->description,
                'week_days' => $ride1->week_days,
                'repeats_until' => $ride1->repeats_until,
                'done' => $ride1->done,
                'driver' => $this->user->toArray(),
                'riders' => [],
                'feedback' => null
            ],
            [
                'id' => $ride2->id,
                'myzone' => $ride2->myzone,
                'neighborhood' => $ride2->neighborhood,
                'going' => $ride2->going,
                'place' => $ride2->place,
                'route' => $ride2->route,
                'routine_id' => $ride2->routine_id,
                'hub' => $ride2->hub,
                'slots' => $ride2->slots,
                'mytime' => $ride2->date->format('H:i:s'),
                'mydate' => $ride2->date->format('Y-m-d'),
                'description' => $ride2->description,
                'week_days' => $ride2->week_days,
                'repeats_until' => $ride2->repeats_until,
                'done' => $ride2->done,
                'driver' => $user2->toArray(),
                'riders' => [$this->user->toArray()],
                'feedback' => null
            ]
        ]);
    }

    public function testGetHistoryCount()
    {
        $user2 = factory(User::class)->create();

        $ride1 = factory(Ride::class)->create(['done' => true]); // offered
        $ride1->users()->attach($this->user, ['status' => 'driver']);

        $ride1 = factory(Ride::class)->create(['done' => true]); // offered
        $ride1->users()->attach($this->user, ['status' => 'driver']);

        $ride2 = factory(Ride::class)->create(['done' => true]); // taken
        $ride2->users()->attach($this->user, ['status' => 'accepted']);
        $ride2->users()->attach($user2, ['status' => 'driver']);

        $ride3 = factory(Ride::class)->create(['done' => false]); // incomplete
        $ride3->users()->attach($this->user, ['status' => 'driver']);

        $ride4 = factory(Ride::class, 'next')->create(); // incomplete
        $ride4->users()->attach($this->user, ['status' => 'accepted']);

        $ride5 = factory(Ride::class)->create(['done' => true]); // from other user
        $ride5->users()->attach($user2, ['status' => 'driver']);

        $ride6 = factory(Ride::class)->create(['done' => true]); // rejected
        $ride6->users()->attach($this->user, ['status' => 'rejected']);

        $response = $this->json('GET', 'ride/getRidesHistoryCount/' . $this->user->id, [], $this->headers);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'offeredCount' => 2,
            'takenCount' => 1
        ]);
    }

    public function testSendChatMessage()
    {
        $this->push->shouldReceive('sendDataToRideMembers')->once();

        // Create fake ride with the user as driver
        $ride = factory(Ride::class)->create();
        $ride->users()->attach($this->user, ['status' => 'driver']);

        $request = [
            'message' => str_random(255)
        ];

        $response = $this->json('POST', 'ride/' . $ride->id . '/chat', $request, $this->headers);
        $response->assertResponseOk();
    }
}