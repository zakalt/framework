<?php

namespace Illuminate\Tests\Integration\Database;

use Illuminate\Database\Eloquent\Casts\AsAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DatabaseEloquentModelAttributeCastingTest extends DatabaseTestCase
{
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed()
    {
        Schema::create('test_eloquent_model_with_custom_casts', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
        });
    }

    public function testBasicCustomCasting()
    {
        $model = new testEloquentModelWithAttributeCast;
        $model->uppercase = 'taylor';

        $this->assertSame('TAYLOR', $model->uppercase);
        $this->assertSame('TAYLOR', $model->getAttributes()['uppercase']);
        $this->assertSame('TAYLOR', $model->toArray()['uppercase']);

        $unserializedModel = unserialize(serialize($model));

        $this->assertSame('TAYLOR', $unserializedModel->uppercase);
        $this->assertSame('TAYLOR', $unserializedModel->getAttributes()['uppercase']);
        $this->assertSame('TAYLOR', $unserializedModel->toArray()['uppercase']);

        $model->syncOriginal();
        $model->uppercase = 'dries';
        $this->assertSame('TAYLOR', $model->getOriginal('uppercase'));

        $model = new testEloquentModelWithAttributeCast;
        $model->uppercase = 'taylor';
        $model->syncOriginal();
        $model->uppercase = 'dries';
        $model->getOriginal();

        $this->assertSame('DRIES', $model->uppercase);

        $model = new testEloquentModelWithAttributeCast;

        $model->address = $address = new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House');
        $address->lineOne = '117 Spencer St.';
        $this->assertSame('117 Spencer St.', $model->getAttributes()['address_line_one']);

        $model = new testEloquentModelWithAttributeCast;

        $model->setRawAttributes([
            'address_line_one' => '110 Kingsbrook St.',
            'address_line_two' => 'My Childhood House',
        ]);

        $this->assertSame('110 Kingsbrook St.', $model->address->lineOne);
        $this->assertSame('My Childhood House', $model->address->lineTwo);

        $this->assertSame('110 Kingsbrook St.', $model->toArray()['address_line_one']);
        $this->assertSame('My Childhood House', $model->toArray()['address_line_two']);

        $model->address->lineOne = '117 Spencer St.';

        $this->assertFalse(isset($model->toArray()['address']));
        $this->assertSame('117 Spencer St.', $model->toArray()['address_line_one']);
        $this->assertSame('My Childhood House', $model->toArray()['address_line_two']);

        $this->assertSame('117 Spencer St.', json_decode($model->toJson(), true)['address_line_one']);
        $this->assertSame('My Childhood House', json_decode($model->toJson(), true)['address_line_two']);

        $model->address = null;

        $this->assertNull($model->toArray()['address_line_one']);
        $this->assertNull($model->toArray()['address_line_two']);

        $model->options = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $model->options = ['foo' => 'bar'];
        $model->options = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $this->assertEquals(['foo' => 'bar'], $model->options);

        $this->assertSame(json_encode(['foo' => 'bar']), $model->getAttributes()['options']);

        $model = new testEloquentModelWithAttributeCast(['options' => []]);
        $model->syncOriginal();
        $model->options = ['foo' => 'bar'];
        $this->assertTrue($model->isDirty('options'));

        $model = new testEloquentModelWithAttributeCast;
        $model->birthday_at = now();
        $this->assertIsString($model->toArray()['birthday_at']);
    }

    public function testGetOriginalWithCastValueObjects()
    {
        $model = new TestEloquentModelWithAttributeCast([
            'address' => new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House'),
        ]);

        $model->syncOriginal();

        $model->address = new AttributeCastAddress('117 Spencer St.', 'Another house.');

        $this->assertSame('117 Spencer St.', $model->address->lineOne);
        $this->assertSame('110 Kingsbrook St.', $model->getOriginal('address')->lineOne);
        $this->assertSame('117 Spencer St.', $model->address->lineOne);

        $model = new TestEloquentModelWithAttributeCast([
            'address' => new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House'),
        ]);

        $model->syncOriginal();

        $model->address = new AttributeCastAddress('117 Spencer St.', 'Another house.');

        $this->assertSame('117 Spencer St.', $model->address->lineOne);
        $this->assertSame('110 Kingsbrook St.', $model->getOriginal()['address_line_one']);
        $this->assertSame('117 Spencer St.', $model->address->lineOne);
        $this->assertSame('110 Kingsbrook St.', $model->getOriginal()['address_line_one']);

        $model = new TestEloquentModelWithAttributeCast([
            'address' => new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House'),
        ]);

        $model->syncOriginal();

        $model->address = null;

        $this->assertNull($model->address);
        $this->assertInstanceOf(AttributeCastAddress::class, $model->getOriginal('address'));
        $this->assertNull($model->address);
    }

    public function testOneWayCasting()
    {
        $model = new TestEloquentModelWithAttributeCast;

        $model->password = 'secret';

        $this->assertEquals(hash('sha256', 'secret'), $model->password);
        $this->assertEquals(hash('sha256', 'secret'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret'), $model->password);

        $model->password = 'secret2';

        $this->assertEquals(hash('sha256', 'secret2'), $model->password);
        $this->assertEquals(hash('sha256', 'secret2'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret2'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret2'), $model->password);
    }

    public function testSettingRawAttributesClearsTheCastCache()
    {
        $model = new TestEloquentModelWithAttributeCast;

        $model->setRawAttributes([
            'address_line_one' => '110 Kingsbrook St.',
            'address_line_two' => 'My Childhood House',
        ]);

        $this->assertSame('110 Kingsbrook St.', $model->address->lineOne);

        $model->setRawAttributes([
            'address_line_one' => '117 Spencer St.',
            'address_line_two' => 'My Childhood House',
        ]);

        $this->assertSame('117 Spencer St.', $model->address->lineOne);
    }
}

class testEloquentModelWithAttributeCast extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]
     */
    protected $guarded = [];

    #[AsAttribute]
    public function uppercase()
    {
        return new Attribute(
            get: fn ($value) => strtoupper($value),
            set: fn ($value) => strtoupper($value),
        );
    }

    #[AsAttribute]
    public function address()
    {
        return new Attribute(
            get: function ($value, $attributes) {
                if (is_null($attributes['address_line_one'])) {
                    return;
                }

                return new AttributeCastAddress($attributes['address_line_one'], $attributes['address_line_two']);
            },
            set: function ($value) {
                if (is_null($value)) {
                    return [
                        'address_line_one' => null,
                        'address_line_two' => null,
                    ];
                }

                return ['address_line_one' => $value->lineOne, 'address_line_two' => $value->lineTwo];
            },
        );
    }

    #[AsAttribute]
    public function options()
    {
        return new Attribute(
            get: fn ($value) => json_decode($value, true),
            set: fn ($value) => json_encode($value),
        );
    }

    #[AsAttribute]
    public function birthdayAt()
    {
        return new Attribute(
            get: fn ($value) => Carbon::parse($value),
            set: fn ($value) => $value->format('Y-m-d'),
        );
    }

    #[AsAttribute]
    public function password()
    {
        return new Attribute(
            set: fn ($value) => hash('sha256', $value)
        );
    }
}

class AttributeCastAddress
{
    public $lineOne;
    public $lineTwo;

    public function __construct($lineOne, $lineTwo)
    {
        $this->lineOne = $lineOne;
        $this->lineTwo = $lineTwo;
    }
}