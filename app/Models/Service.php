<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Service
 * 
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string|null $image
 * @property string|null $category
 * @property string|null $audience
 * @property string|null $location
 * @property string|null $whatsapp
 * @property string $status
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 *
 * @package App\Models
 */
class Service extends Model
{
	use SoftDeletes, HasPublicationStatus;

	protected $guarded = [];

	protected $casts = [
		'sort_order' => 'int',
	];
}
