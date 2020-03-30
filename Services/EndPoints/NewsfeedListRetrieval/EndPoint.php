<?php

require_once("../../HelperClasses/CommonEndPointLogic.php");
require_once("../../HelperClasses/ValidationHelper.php");
require_once("../../HelperClasses/SuccessStates.php");

CommonEndPointLogic::ValidateHTTPGETRequest();

$username = $_GET["username"];
$hashedPassword = $_GET["hashedPassword"];
$postsCount = $_GET["postsCount"];
    
if ($username == null || $hashedPassword == null || $postsCount == null) {
    $failureResponseStatus = CommonEndPointLogic::GetFailureResponseStatus("NULL_CREDENTIAL");

    echo json_encode($failureResponseStatus), PHP_EOL;
    http_response_code(StatusCodes::BAD_REQUEST);
    die();
}   

$responseStatus = CommonEndPointLogic::ValidateUserCredentials($username, $hashedPassword);


$getPostsQuery = "
SELECT ID, title, content, URL, DateTime_Created FROM NewsFeed_Posts Order by DateTime_Created LIMIT :postsCount;
    ";

$getTagsQuery = "
SELECT t.title AS tagTitle FROM newsfeed_tags t join newsfeed_posts_tags_assignations r ON t.ID = r.Newsfeed_Tag_ID 
JOIN newsfeed_posts p ON p.ID = r.Newsfeed_Post_ID
WHERE p.ID = :postID;
    ";


$postsArray = array();
try {
    DatabaseManager::Connect();

    $getPosts = DatabaseManager::PrepareStatement($getPostsQuery);
    $getPosts->bindParam(":postsCount", $postsCount);
    $getPosts->execute();

    while($getPostsRow = $getPosts->fetch(PDO::FETCH_ASSOC)){
        $post = new Posts();
        $post->set_title($getPostsRow["title"]);
        $post->set_content($getPostsRow["content"]);
        $post->set_URL($getPostsRow["URL"]);
        $post->set_dateCreated($getPostsRow["DateTime_Created"]);
        $post->set_title($getPostsRow["title"]);

        $getTags = DatabaseManager::PrepareStatement($getTagsQuery);
        $getTags->bindParam(":postsID", $getPostsRow["ID"]);
        $getTags->execute();
        while($getTagsRow = $getTags->fetch(PDO::FETCH_ASSOC)){
            $post->add_tags($getTagsRow["tagTitle"]);
        }

        array_push($postsArray,$post);
    }

    DatabaseManager::Disconnect();
}
catch (Exception $databaseException) {
    $response = CommonEndPointLogic::GetFailureResponseStatus("BD_EXCEPT");
    echo json_encode($response), PHP_EOL;
    http_response_code(StatusCodes::OK);
    die();
}


$responseSuccess = CommonEndPointLogic::GetSuccessResponseStatus();
echo json_encode($responseSuccess), PHP_EOL;
echo json_encode($postsArray), PHP_EOL;
http_response_code(StatusCodes::OK);

class Posts {
    public $title;
    public $content;
    public $URL;
    public $dateCreated;
    public $tags;
    function set_title($title) {
      $this->title = $title;
    }
    function get_title() {
      return $this->title;
    }
    function set_content($content) {
        $this->content = $content;
    }
    function get_content() {
      return $this->content;
    }
    function set_URL($URL) {
      $this->URL = $URL;
    }
    function get_URL() {
      return $this->URL;
    }
    function set_dateCreated($dateCreated) {
      $this->dateCreated = $dateCreated;
    }
    function get_dateCreated() {
      return $this->dateCreated;
    }
    function add_tags($tag) {
      array_push($this->tags, $tag);
    }
    function get_tags() {
      return $this->tags;
    }
  }
//   class Tags {
//     public $title;
//     function set_title($title) {
//         $this->title = $title;
//     }
//     function get_title() {
//       return $this->title;
//     }
//   }
?>