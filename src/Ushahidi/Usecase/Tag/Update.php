<?php

/**
 * Ushahidi Platform Admin Tag Update Use Case
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Platform
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Usecase\Tag;

use Ushahidi\Usecase;
use Ushahidi\Data;
use Ushahidi\Entity\Tag;
use Ushahidi\Tool\Validator;
use Ushahidi\Exception\ValidatorException;
use Ushahidi\Exception\NotFoundException;

class Update implements Usecase
{
	private $repo;
	private $valid;

	private $updated = [];

	public function __construct(UpdateTagRepository $repo, Validator $valid)
	{
		$this->repo  = $repo;
		$this->valid = $valid;
	}

	public function interact(Data $input)
	{
		if ($input->role) {
			$role = $input->role;
			$input->role = json_encode($role);
		}

		$tag = $this->repo->get($input->id);

		if (!$tag->id) {
			throw new NotFoundException(sprintf(
				'Tag %d does not exist',
				$input->id
			));
		}

		// We only want to work with values that have been changed
		$update = $input->getDifferent($tag->asArray());

		if (!$this->valid->check($update)) {
			throw new ValidatorException("Failed to validate tag", $this->valid->errors());
		}

		// Determine what changes to make in the tag
		$this->updated = $update->asArray();

		$this->repo->updateTag($tag->id, $this->updated);

		// Reflect the changes in the tag
		$tag->setData($this->updated);

		return $tag;
	}

	public function getUpdated()
	{
		return $this->updated;
	}
}
