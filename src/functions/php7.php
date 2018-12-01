<?php 
// null coalescing operator
// test it with ?username=foo
echo '<pre>';
echo ($_GET['username']) ?? 'not passed';
echo '<br>';

$value_1 = 10;
$value_2 = 10;
//spaceship operator
if ( ( $value_1 <=> $value_2 ) == 0 ) echo "Both values are equal";
if ( ( $value_1 <=> $value_2 ) == -1 ) echo "First value is lesser than the second value";
if ( ( $value_1 <=> $value_2 ) == 1 ) echo "First value is greater than the second value";
echo '<br>';

//assertion
//assert(true == false);

//variadic functions
function concatenate($transform, ...$strings) {
    $string = '';
    foreach($strings as $piece) {
        $string .= $piece;
    }
    return($transform($string));
}

echo concatenate("strtoupper", "I'd ", "like ", 4 + 2, " apples");
echo '<br>';

//Argument unpacking
$fruit_stack = array("Mango", "Banana", "Jackfruit");
$new_fruits = array("Apple", "Orange", "Valencia", "Pear");
array_push($fruit_stack, ...$new_fruits);
print_r($fruit_stack); 

//A generator can yield as many times as it needs to 
//in order to provide the values to be iterated over.
function isPrime($n) {
     $flag = true;
    $x = 0;
    for ( $i = 2; $i <= ($n + $x) / 2; $i++ ) {      
        if ( $n % $i == 0) {
            $flag = false;            
            break;
        }
        if ($i % 3 == 0) $x--;
    }
    return $flag;
}

function generateFirstHundreadPrimeNumbers() {
    $count = 0;
    $number = 2;
    do { 
        if ( isPrime($number) ) {
            yield $number;
            $count++;
        }
        $number++;            
    } while( $count < 100 );
}

$count = 1;

foreach (generateFirstHundreadPrimeNumbers() as $number) {    
    echo "<br> Prime $count => $number ";
    $count++;
}

//PHP functional programming
// array_pop, array_reduce, array_map

//Nullable Types - 
//Type declarations for parameters and return values can now be marked as nullable 
//by prefixing the type name with a question mark


//Symmetric array destructuring
//The shorthand array syntax ([]) may now be used to destructure arrays for assignments
//like the list() syntax

var_dump("ABCDFGH"[2]);
var_dump("ABCDFGH"[-2]);

if($_SERVER['REQUEST_METHOD'] == 'GET') {
    echo "this is a get request\n";
    print_r($_GET);
} elseif($_SERVER['REQUEST_METHOD'] == 'PUT') {
    echo "this is a put request\n";
    parse_str(file_get_contents("php://input"), $put_vars);
    print_r($put_vars);
}
