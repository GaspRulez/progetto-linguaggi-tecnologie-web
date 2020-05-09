<?php


class GroupsController extends \chriskacerguis\RestServer\RestController
{

	public function __construct($config = 'rest')
	{
		parent::__construct($config);
	}


	public function createGroup_post() {
		$tokenData = validateAuthorizationToken($this->input->get_request_header("Authorization"));

		if ($tokenData["status"]) {
			$groupName = $this->input->post("groupName");
			$groupDesc = $this->input->post("groupDesc");
			$ownerId = $tokenData["data"]["userId"];

			$errors = array(
				"groupNameHasError" => false,
				"groupDescHasError" => false
			);

			// Check minlength titolo
			if (strlen($groupName) < 1) {
				$errors["groupNameHasError"] = true;
				return $this->response(buildServerResponse(
					false, "Il nome del gruppo non può essere vuoto.", $errors), 200);
			}

			// Check maxlength titolo
			if (strlen($groupName) > 255) {
				$errors["groupNameHasError"] = true;
				return $this->response(buildServerResponse(
					false, "Il nome del gruppo non può contenere più di 255 caratteri.", $errors), 200);
			}

			// Check maxlength descrizione
			if (strlen($groupDesc) > 255) {
				$errors["groupDescHasError"] = true;
				return $this->response(buildServerResponse(
					false, "La descrizione del gruppo non può contenere più di 255 caratteri.", $errors), 200);
			}

			// Insert group in db
			$groupId = $this->GroupsModel->createGroup($groupName, $groupDesc, $ownerId);

			// Add creator as admin
			$this->UserModel->addMembership(array("user_id" => $ownerId, "group_id" => $groupId, "is_admin" => "1"));

			// Create group chat
			$this->ChatModel->createGroupChat($groupId);

			return $this->response(buildServerResponse(true, "ok"), 200);
		}

		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}


	public function sendInvitations_post() {
		$tokenData = validateAuthorizationToken($this->input->get_request_header('Authorization'));
		if($tokenData["status"]) {
			$userId = $tokenData["data"]["userId"];
			$groupId = $this->input->post('groupId');

			if(!FILTER_VAR($groupId, FILTER_VALIDATE_INT)) // è un intero l'id del gruppo
				return $this->response(buildServerResponse(false, "L'identificatore del gruppo non è valido."), 200);

			$groupInfo = $this->GroupsModel->getGroupById($groupId);
			if(count($groupInfo) <= 0)
				return $this->response(buildServerResponse(false, "Questo gruppo non esiste."), 200); // il gruppo non esiste.

			if(!$this->GroupsModel->isGroupMember($userId, $groupId))
				return $this->response(buildServerResponse(false, "Non puoi invitare utenti in un gruppo di cui non fai parte."), 200);

			$users = json_decode($this->input->post('users'));

			if(count($users) <= 0)
				return $this->response(buildServerResponse(false, "Seleziona degli utenti da invitare."), 200);

			foreach($users as $key => $value) {
				if(!FILTER_VAR($value->id, FILTER_VALIDATE_INT)) // intero non valido.
					continue;

				$getUser = $this->UserModel->getUserById($value->id);
				if(count($getUser) <= 0)
					continue; // utente non esiste.

				if($userId == $value->id)
					continue; // mi auto invito e non va bene.

				// controlliamo che l'utente che stiamo invitando non sia già nel gruppo
				if($this->GroupsModel->isGroupMember($value->id, $groupId))
					continue;

				$invitationAlreadyExists = $this->GroupsModel->invitationAlreadyExists($userId, $value->id, $groupId);
				$data = array("from_id" => $userId, "to_id" => $value->id, "group_id" => $groupId, "invited_at" => "now()");
				$this->GroupsModel->addInvitation($data, $invitationAlreadyExists);
			}


			return $this->response(buildServerResponse(true, "ok"));

		}
		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}


	public function getUserInvitations_post() {
		$tokenData = validateAuthorizationToken($this->input->get_request_header('Authorization'));
		if($tokenData["status"]) {
			$userId = $tokenData["data"]["userId"];
			$user = $this->UserModel->getUserById($userId);
			if(count($user) <= 0)
				return $this->response(buildServerResponse(false, "Utente non autenticato."), 200);

			$userInvitations = $this->GroupsModel->getUserInvitation($userId);
			return $this->response(buildServerResponse(true, "ok", array("invitations" => $userInvitations)), 200);
		}

		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}


	public function resetUserCountInvitations_post() {
		$tokenData = validateAuthorizationToken($this->input->get_request_header('Authorization'));
		if($tokenData["status"]) {
			$userId = $tokenData["data"]["userId"];
			$user = $this->UserModel->getUserById($userId);
			if(count($user) <= 0)
				return $this->response(buildServerResponse(false, "Utente non autenticato."), 200);

			if($this->UserModel->resetCountNotification($userId))
				return $this->response(buildServerResponse(true, "ok"), 200);
		}

		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}

	public function deleteGroup_post() {
		$tokenData = validateAuthorizationToken($this->input->get_request_header('Authorization'));
		if($tokenData["status"]) {
			$userId = $tokenData["data"]["userId"];
			$user = $this->UserModel->getUserById($userId);
			if (count($user) <= 0)
				return $this->response(buildServerResponse(false, "Utente non autenticato."), 200);

			$groupId = $this->input->post('groupId');

			if(!FILTER_VAR($groupId, FILTER_VALIDATE_INT)) // parametro groupId: è un intero?
				return $this->response(buildServerResponse(false, "Id gruppo non valido."), 200);
			$group = $this->GroupsModel->getGroupById($groupId);

			if(count($group) <= 0) // controllo che il gruppo esista
				return $this->response(buildServerResponse(false, "Id gruppo non valido."), 200);

			if($group[0]->group_owner != $userId) // sono il proprietario del gruppo?
				return $this->response(buildServerResponse(false, "Solo il proprietario può eliminare il gruppo"), 200);

			if($this->GroupsModel->deleteGroup($groupId))
				return $this->response(buildServerResponse(true, "ok"));
		}

		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}


	public function replyInvitation_post() {
		$tokenData = validateAuthorizationToken($this->input->get_request_header('Authorization'));
		if($tokenData["status"]) {
			$userId = $tokenData["data"]["userId"];
			$user = $this->UserModel->getUserById($userId);
			if(count($user) <= 0)
				return $this->response(buildServerResponse(false, "Utente non autenticato."), 200);

			$typeReply = $this->input->post('type');
			if($typeReply != 1 && $typeReply != 0)
				return $this->response(buildServerResponse(false, "Invito non valido."), 200);

			$groupId = $this->input->post("groupId");
			if(!FILTER_VAR($groupId, FILTER_VALIDATE_INT))
				return $this->response(buildServerResponse(false, "Invito non valido."), 200);

			$group = $this->GroupsModel->getGroupById($groupId);
			if(count($group) <= 0)
				return $this->response(buildServerResponse(false, "L'invito al gruppo non è più valido poiché il gruppo non esiste."), 200);

			if(!$this->UserModel->existsInvitation($userId, $groupId)) // se non esiste un invito per questo utente per questo gruppo allora non va avanti
				return $this->response(buildServerResponse(false, "Invito non valido."), 200);

			// se il type è 1 allora accetto l'invito, se è 0 no.
			if($typeReply == 0) {
				if($this->UserModel->deleteNotificationGroupForUser($userId, $groupId))
					return $this->response(buildServerResponse(true, "ok"), 200);
				else
					return $this->response(buildServerResponse(false, "Errore durante l'eliminazione degli inviti."), 200);
			} else {

				// l'utente ha accettato e quindi dobbiamo aggiungerlo al gruppo.
				$membership = array("user_id" => $userId, "group_id" => $groupId, "is_admin" => "0");
				$groupData = $this->GroupsModel->getAllDataGroup($group[0]->id);
				if($this->UserModel->addMembership($membership)) {
					if($this->UserModel->deleteNotificationGroupForUser($userId, $groupId))
						return $this->response(buildServerResponse(true, "ok", array("group" => $groupData[0])),  200);
				}
			}
		}
		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}


	public function createPost_post() {
		$tokenData = validateAuthorizationToken($this->input->get_request_header('Authorization'));
		if($tokenData["status"]) {
			$userId = $tokenData["data"]["userId"];
			$user = $this->UserModel->getUserById($userId);
			if(count($user) <= 0)
				return $this->response(buildServerResponse(false, "Utente non autenticato."), 200);

			$groupId = $this->input->post('groupId');
			$postText = $this->input->post('postText');
			if(!$this->GroupsModel->isGroupMember($userId, $groupId))
				return $this->response(buildServerResponse(false, "Non puoi creare un post in un gruppo al quale non appartieni."), 200);

			if(strlen($postText) <= 0 || strlen(trim($postText)) <= 0)
				return $this->response(buildServerResponse(false, "Inserisci almeno un carattere al tuo post."), 200);

			$config['upload_path'] = './uploads/groupsFiles/'.$groupId.'/';
			$config['allowed_types'] = '*';
			$config['encrypt_name'] = true; // codifica il nome del file caricato.
			$this->load->library('upload', $config);

			/* check directory */

			if(!is_dir('./uploads/groupsFiles/'.$groupId.'/'))
				mkdir('./uploads/groupsFiles/'.$groupId.'/', TRUE);

			$files = $_FILES;
			$filesArray = array();
			if(count($files) > 0) {
				$filesCount = count($_FILES["files"]["name"]);
				if ($filesCount > 0) {
					for ($i = 0; $i < $filesCount; $i++) {
						/* prendo le informazioni del file corrente e le metto dentro una variabile 'file' in $_FILES. */
						$name = $files['files']['name'][$i];
						$type = $files['files']['type'][$i];
						$tmp_name = $files['files']['tmp_name'][$i];
						$error = $files['files']['error'][$i];
						$size = $files['files']['size'][$i];

						$_FILES['file'] = array("name" => $name, "type" => $type, "tmp_name" => $tmp_name, "error" => $error, "size" => $size);

						if ($this->upload->do_upload("file")) {
							$uploadedData = $this->upload->data(); // prendo le info del file uploadato
							$filesArray[] = array("originalName" => $name, "serverName" => $uploadedData["file_name"]);
						} else {
							return $this->response(buildServerResponse(false, $this->upload->display_errors()), 200);
						}
					}
				}
			}

			// struttura tabella posts: user_id, group_id, file_uploaded, post_text, created_at
			$data = array(
				"user_id" => $userId,
				"group_id" => $groupId,
				"file_uploaded" => json_encode($filesArray),
				"post_text" => $postText,
				"created_at" => "now()"
			);

			if($this->GroupsModel->addPostToGroup($data))
				return $this->response(buildServerResponse(true, "Post creato con successo.", array("filesUploaded" => $filesArray)), 200);

			return $this->response(buildServerResponse(false, "Errore nell'inserimento del post.", ), 200);

		}
		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}

	public function addComment_post() {
		// parametri da passare: header Authorization con token utente, per quanto riguarda i dati post sono: groupId, postId e commentText.
		$tokenData = validateAuthorizationToken($this->input->get_request_header('Authorization'));
		if($tokenData["status"]) {
			$userId = $tokenData["data"]["userId"];
			$user = $this->UserModel->getUserById($userId);
			if (count($user) <= 0)
				return $this->response(buildServerResponse(false, "Utente non autenticato."), 200);

			$groupId = $this->input->post('groupId');
			$commentText = $this->input->post('commentText');
			if (!$this->GroupsModel->isGroupMember($userId, $groupId))
				return $this->response(buildServerResponse(false, "Non puoi creare un post in un gruppo al quale non appartieni."), 200);

			if (strlen($commentText) <= 0 || strlen(trim($commentText)) <= 0)
				return $this->response(buildServerResponse(false, "Inserisci almeno un carattere al tuo post."), 200);

			$postId = $this->input->post('postId');
			if(!FILTER_VAR($postId, FILTER_VALIDATE_INT))
				return $this->response(buildServerResponse(false, "Post id non valido."), 200);

			// controlliamo che il post appartenga a quel gruppo.
			$postData = $this->GroupsModel->getPostFromGroup($postId, $groupId);
			if(count($postData) <= 0)
				return $this->response(buildServerResponse(false, "Il post non appartiene al gruppo."), 200);

			$data = array(
				"user_id" => $userId,
				"post_id" => $postId,
				"comment_text" => $commentText,
				"created_at" => "now()"
			);

			$commentId = $this->GroupsModel->addComment($data);
			// se va a buon fine possiamo anche restituire il commento da aggiungere poi al redux (da fare in fase di frontend).
			if(FILTER_VAR($commentId, FILTER_VALIDATE_INT))
				return $this->response(buildServerResponse(true, "Commento aggiunto con successo.", array("comment" => array())), 200);

		}

		return $this->response(buildServerResponse(false, "Errore autorizzazione token."), 200);
	}

}
