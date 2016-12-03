<?php
// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');
ini_set('memory_limit','5000M');
include('simple_html_dom.php');
$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$results = false;
function Bold($text, $str) {
  for($i=0;$i<sizeof($text);$i++) {
        $str=str_replace($text[$i], "<strong>".$text[$i]."</strong>", $str);
    }
    return $str;             
}
if ($query)
{
  // The Apache Solr Client library should be on the include path
  // which is usually most easily accomplished by placing in the
  // same directory as this script ( . or current directory is a default
  // php include path entry in the php.ini)
  require_once('Apache/Solr/Service.php');
  require_once('SpellCorrector.php');
  // create a new solr service instance - host, port, and corename
  // path (all defaults in this example)
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/csci572/');
  $correct_word="";
//  $correct_word=SpellCorrector::correct($query);
  $q=explode(" ",$query);
  $i=0;
  while($i<sizeof($q)) {
        $correct_word.=SpellCorrector::correct($q[$i]);
        $correct_word.=" ";
        $q[$i]=ucfirst($q[$i]);
        $i++;
  }

  if(strtolower(trim($correct_word))==strtolower(trim($query))) {
    $correct_word="";
  } 
  // if magic quotes is enabled then stripslashes will be needed
  if (get_magic_quotes_gpc() == 1)
  {
    $query = stripslashes($query);
  }
  $params=[];
  if(array_key_exists("pagerank", $_REQUEST)) {
    $params['sort']="pageRankFile desc";
  }

  // in production code you'll always want to use a try /catch for any
  // possible exceptions emitted by searching (i.e. connection
  // problems or a query parsing error)
  try
  {
    $results = $solr->search($query, 0, $limit,$params);
  }
  catch (Exception $e)
  {
   // in production you'd probably log or email this error to an admin
   // and then show a special message to the user but for this example
   // we're going to show the full exception
   die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  }
}
?>
<html>
  <head>
  <style>
    a:link {
      color: green;
    }
  </style>
  <title>PHP Solr Client Example</title>
   <link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <script src="http://code.jquery.com/jquery-1.10.2.js"></script>
    <script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
  </head>
  <body>
  <form accept-charset="utf-8" method="get">
  <label for="q">Search:</label>
  <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/>
  <input type="checkbox" name="pagerank">Use Page Rank</input>
  <input type="submit"/>
  <input type="reset" onclick="resetForm();" />
  </form>
<?php
  $url;
  // display results
  if ($results)
  {
   $total = (int) $results->response->numFound;
   $start = min(1, $total);
   $end = min($limit, $total);
?>
<?php 
    if($correct_word!=""):
?>
<p>Did you mean: <a href="http://localhost:8888/solr-php-client-master/?q=<?php echo $correct_word?>"><?php echo $correct_word ?></a> </p> <?php endif; ?>
  <div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
  <ol>
<?php
  $url_array=[];
  if (($handle = fopen("mergeDataFile.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $url_array[$data[0]]=$data[1];
    }
    fclose($handle);
  }
  // iterate result documents
  foreach ($results->response->docs as $doc)
  {
?>

 
<?php
  // iterate document fields / values
  foreach ($doc as $field => $value)
  {
    if($field=="id") {
      $url=$value;
    }
?>
  
 
<?php
  }
?>
  <?php 
        $urls=explode("/",$url);
        $size=sizeof($urls)-1;
        $url_name=$urls[$size];
         
      ?>
 
   
      <?php
        $html = file_get_html($url_array[$url_name]);
        $result="";
        //to find h1 headers from a webpage
        $headlines = array();
        foreach($html->find('body') as $header) {
            foreach ($header->find('p') as $h1) {
                # code...
                $headlines[] = $h1->plaintext;
            }
           for($i=0;$i<sizeof($headlines);$i++) {
                for($j=0;$j<sizeof($q);$j++) {
                    if (strpos(strtolower($headlines[$i]), strtolower($q[$j])) !== false) {
                        if(strlen($result)<256) {
                            $result.=Bold($q,$headlines[$i]);
                        }
                        break;
                    } else {
                        continue;
                    }
                }
            } 
        }
        
      ?>
 <b><?php echo $doc->title; ?></b><br />
 <?php echo "<a href='$url_array[$url_name]' target='_blank'>".$url_array[$url_name]."</a>"; ?>
 <p><?php 
 if(strlen(trim($result))==0) {
    for($k=0;$k<sizeof($q);$k++) {
      if (strpos(strtolower($doc->title), strtolower($q[$k])) !== false) {
        echo "...".Bold($q,$doc->title)."...";
      }
      break;
    }
 } else {
    echo "...".$result."...";
 }
?></p>
 
<?php
 }
?>
 </ol>
<?php
  }
?>
 <script>

 function resetForm() {
    window.location="index.php";
 }

 function isStopWord(stopword) {
  var regex=new RegExp("\\b"+stopword+"\\b","i");
  return stopWords.search(regex) < 0 ? false : true;
 }

  $(function(){
    var url_start="http://localhost:8983/solr/csci572/suggest?q=";
    var url_end="&wt=json";
            $("#q").autocomplete({
      source : function(request,response) {
        var input=$("#q").val().toLowerCase().split(" ").pop(-1);
        var URL=url_start+input+url_end;
        $.ajax({
          url : URL,
          success : function(data) {
            var input=$("#q").val().toLowerCase().split(" ").pop(-1);
            var suggestions=data.suggest.suggest[input].suggestions;
            suggestions=$.map(suggestions,function(value,index){
              var prefix="";
              var query=$("#q").val();
              var queries=query.split(" ");
              if(queries.length>1) {
                var lastIndex=query.lastIndexOf(" ");
                prefix=query.substring(0,lastIndex+1).toLowerCase();
              }
              if (prefix == "" && isStopWord(value.term)) {
                return null;
              }
               if(!/^[0-9a-zA-Z]+$/.test(value.term)) {
                return null;
              }
              return prefix+value.term;
            });
            response(suggestions.slice(0,5));
          },
          dataType: 'jsonp',
          jsonp: 'json.wrf'
        });  
      },
      minLength: 1 
    });
  });
      var stopWords = "a,able,about,above,across,after,all,almost,also,am,among,can,an,and,any,are,as,at,be,because,been,but,by,cannot,could,dear,did,do,does,either,else,ever,every,for,from,get,got,had,has,have,he,her,hers,him,his,how,however,i,if,in,into,is,it,its,just,least,let,like,likely,may,me,might,most,must,my,neither,no,nor,not,of,off,often,on,only,or,other,our,own,rather,said,say,says,she,should,since,so,some,than,that,the,their,them,then,there,these,they,this,tis,to,too,twas,us,wants,was,we,were,what,when,where,which,while,who,whom,why,will,with,would,yet,you,your,not";
    </script>
 </body>
 </html>