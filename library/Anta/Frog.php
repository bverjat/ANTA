<?php/** * @package Anta *//** * anta frog...  */class Anta_Frog{		/**	 * retrieve a lisst of REGEXP results.	 * the filters object must contain: a query 	 */	public function regexp( Application_Model_User $antaUser, $filters ){				$orderBy = implode(",", empty($filters->orders)? array("date DESC", "id_document DESC","position ASC"):$filters->orders );				# create result set object		$matches = new Anta_Frog_Matches(); 		$whereClause = "";		$binds = array();				# 1. filter documents by documents id		if( !(empty( $filters->docs ))){			$whereClause .= " id_document IN (".implode(",",array_fill( 0, count($filters->docs), "?" ))." ) ";			$binds = array_merge( $binds, $filters->docs );		}				# 2. filter documents by document tags		if( !(empty( $filters->tags ))){			$whereClause .= (strlen($whereClause)>0?"AND ":"")." id_document IN ( 				SELECT id_document FROM anta_{$antaUser->username}.`documents_tags` 				NATURAL JOIN anta_{$antaUser->username}.`tags` 				WHERE id_tag IN (".implode(",",array_fill( 0, count($filters->tags), "?" ))." ) )";			$binds = array_merge( $binds, $filters->tags );		}				$startTime = microtime(true);		# get a list of sentences that matches		$query = "			SELECT SQL_CALC_FOUND_ROWS title, DATE_FORMAT( do.date,'%d/%m/%Y' ) as date, content, position, id_sentence, id_document 			FROM (				SELECT id_document, title, date FROM anta_{$antaUser->username}.documents 				".( strlen( $whereClause )>0?"WHERE {$whereClause}":"")."							) do			INNER JOIN anta_{$antaUser->username}.sentences se USING( id_document ) 			WHERE  se.content REGEXP ? ORDER BY {$orderBy} LIMIT {$filters->offset},{$filters->limit}"		;				$mysqli = Anta_Core::getMysqliConnection();		$stmt = $mysqli->query( $query, array_merge( $binds,  array($filters->query) ) );								// create a list of different document. size property is the number of sentences		while( $row = $stmt->fetchObject() ){			if( !isset( $matches->documents[ $row->id_document ] ) ){ 				$matches->documents[ $row->id_document ] = array(					"title"     => $row->title,					"date"      => $row->date,					"tags"      => Application_Model_DocumentsMapper::getTags( $antaUser, $row->id_document ),					"size"      => Application_Model_SentencesMapper::getNumberOfSentences( $antaUser, $row->id_document )				);			}						$matches->sentences[ $row->id_sentence ] = new Application_Model_Sentence( $row->id_sentence, $row->content, $row->id_document, $row->position, $row->date );					}				// get total items		$stmt = $mysqli->query("SELECT FOUND_ROWS() as totalItems");		$matches->totalItems = $stmt->fetchObject()->totalItems;				$checkPointA = microtime(true) - $startTime;		$startTime = microtime(true);				// test, again... find all documents id containing that regexp		$stmt = $mysqli->query(			"SELECT DISTINCT id_document FROM anta_{$antaUser->username}.documents do				INNER JOIN anta_{$antaUser->username}.sentences se USING( id_document ) 			WHERE se.content REGEXP ? ", array($filters->query)		);		$ids = array();		while( $row = $stmt->fetchObject() ){			$ids[] = $row->id_document;		}										// computate tags statistics, top FIVE for each category using filter in id_documents		foreach( Application_Model_CategoriesMapper::getAll( $antaUser ) as $category ){			 			$matches->categories[ $category->content ] = array();			$query = "SELECT dt.id_tag, dtd.content, count(distinct dt.`id_document`) AS `number_of_documents`				FROM 					anta_{$antaUser->username}.documents_tags dt 									INNER JOIN					anta_{$antaUser->username}.`tags` dtd					USING ( id_tag ) 				WHERE id_document IN(".implode(",",$ids).") AND {$whereClause} ".( strlen( $whereClause )>0? "AND ":"" )." id_category = ? 				GROUP BY dtd.id_tag ORDER BY `number_of_documents` DESC LIMIT 0,5";							$stmt = $mysqli->query($query, array_merge(   $binds,array( $category->id )  ) );			while( $row = $stmt->fetchObject() ){				$matches->categories[ $category->content ][ $row->id_tag ] = $row;			}				};		$checkPointB = microtime(true) - $startTime;				return $matches;		// 					}		}