<?php
namespace WPAward\BusinessLogic;

/**
 * API for our plugin
 * Will be given as a global variable
 * to wordpress in order to easily add
 * own functionality and interact with our abstraction.
 */
class Core {
	private $db;
	private $db_table;

	function __construct( $db ) {
		$this->db = $db;
		$this->db_table = $this->db->prefix . "awards";

		// Signal to wordpress to delete all of our user associated links to "awards" if we delete that user from the db
		add_action( 'delete_user', [$this, 'RemoveAward'], 10, 1 );
	}

	private function __auto_give_award( $award_id ) {
		return get_post_meta( $award_id, 'WPAward_Auto_Give', true );
	}

	/**
	 * Function that will mark an award as given to a user,
	 * which essentially means that we mark the "date_given" time with
	 * an actual date.
	 *
	 * Returns the return value of a `db->update` call
	 *
	 * @param int $user_id  - ID of the user that we are "awarding" the award to
	 * @param int $award_id - ID of the award that we are "awarding"
	 *
	 */
	public function GiveAward( $user_id, $award_id ) {

		if ( empty( $this->GetUserAward( $user_id, $award_id ) ) )
		{
			$this->AssignAward( $user_id, $award_id );
		}

		// Marks the award as given to the user, instead of just assigned to the user
		$award_given = $this->db->update(
			$this->db_table,
			[
				'date_given' => date('Y-m-d G:i:s')
			],
			[
				'user_id' => $user_id,
				'award_id' => $award_id
			],
			NULL,
			[
				'user_id' => '%d',
				'award_id' => '%d'
			]
		);

		return $award_given;
	}

	/**
	 * Function that marks an award as assigned to a user.
	 * We insert a new record into our awards table that relates the award to the user.
	 *
	 * We do check to see if there is an auto-assignment of the award before we finish up our function though.
	 *
	 * @param int $user_id  - ID of the user that we are "awarding" the award to
	 * @param int $award_id - ID of the award that we are "awarding"
	 */
	public function AssignAward( $user_id, $award_id ) {

		$award_assigned = $this->db->insert(
			$this->db_table,
			[
				'user_id' => $user_id,
				'award_id' => $award_id,
			],
			[
				'user_id' => '%d',
				'award_id' => '%d'
			]
		);

		if ( ! $award_assigned )
		{
			return false;
		}

		$auto_give_award = $this->__auto_give_award( $award_id );

		if ( ! empty( $auto_give_award ) )
		{
			$award_given = $this->GiveAward( $award_id, $user_id );
		}

		return true;
	}

	/**
	 * Removes awards from our database.
	 * If "$award_id" is null, then we are going to delete everything in the database with the specific "$user_id"
	 *
	 * @param int $user_id  - ID of the user that we are "awarding" the award to
	 * @param int $award_id - ID of the award that we are "awarding"
	 */
	public function RemoveAward( $user_id, $award_id = NULL ) {

		$where_clause = [
			'user_id' => $user_id,
		];

		$where_format = [
			'user_id' => '%d'
		];

		// Add in our award id to the where clase if it is available. There's a chance that it isn't.
		if ( $award_id )
		{
			$where_clause['award_id'] = $award_id;
			$where_format['award_id'] = '%d';
		}

		$award_deleted = $this->db->delete(
			$this->db_table,
			$where_clause,
			$where_format
		);

		return $award_deleted;
	}

	/**
	 * Function that grabs as many awards assigned to the user as we can based on the parameters given.
	 * For example, if just a user_id is supplied, then we will return all of the awards with that user_id.
	 * If an award_id is supplied along with our user_id then we will probably get only one award. Hopefully
	 *
	 * @param int $user_id  - ID of the user that we are "awarding" the award to
	 * @param int $award_id - ID of the award that we are "awarding"
	 */
	public function GetUserAward( $user_id, $award_id = NULL) {
		$query = "SELECT * FROM {$this->db_table} WHERE `user_id` = %d";

		$prep_params = [ $user_id ];

		if ( ! empty( $award_id ) )
		{
			$query .= " AND `award_id` = %d";
			$prep_params[] = $award_id;
		}

		$prep_query = $this->db->prepare($query, $prep_params);

		$awards = $this->db->get_results($prep_query);

		return $awards;
	}

	/**
	 * Compares $val_1 to $val_2 based on the operator given.
	 * @param mixed $val_1
	 * @param mixed $val_2
	 * @param string $op    - Operator.
	 */
	public function ShouldApplyAward( $val_1, $val_2, $op ) {
		switch ( $op ) {
			case 'gt':
				return $val_1 > $val_2;
			case 'lt':
				return $val_1 < $val_2;
			case 'eq':
				return $val_1 === $val_2;
			case 'gteq':
				return $val_1 >= $val_2;
			case 'lteq':
				return $val_1 <= $val_2;
		}
	}
}
?>