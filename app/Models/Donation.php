<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Donation
 * 
 * @property int $id
 * @property string $donator
 * @property float $amount
 * @property string $project
 * @property string $paymethod
 * @property string $paytransaction
 * @property string|null $description
 * @property Carbon $donation_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class Donation extends Model
{
	use SoftDeletes;

	protected $casts = [
		'amount' => 'float',
		'display_name' => 'bool',
		'donation_at' => 'datetime'
	];

	protected $guarded = [];
}
