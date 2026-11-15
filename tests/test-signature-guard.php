<?php
/**
 * The guard that stops a form-plugin update from taking a site down.
 *
 * Declaring a class whose methods do not fit its parent is a compile-time
 * fatal that no try/catch can stop. If WPForms or Gravity Forms changes a
 * method this plugin overrides, the site would go down on every request. The
 * guard compares what the adapter will declare against the real parent first,
 * and the adapter is skipped, fail closed, when they no longer fit.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/components/consent/signature-guard.php';

// What the adapter would declare: two methods and one property.
$spec = array(
	'methods' => array(
		'render' => array( 3, 2 ), // ( $a, $b, $c = null ): 3 parameters, 2 required
		'init'   => array( 0, 0 ),
	),
	'props'   => array( 'type' ),
);

class PKWYC_Guard_Good {
	public $type;
	public function render( $a, $b, $c = null ) {}
	public function init() {}
	private function helper( &$x ) {} // private: no constraint on the child
}
abstract class PKWYC_Guard_Abstract_Ok {
	public $type;
	abstract public function render( $a, $b );
	abstract public function init();
}
class PKWYC_Guard_Fewer_Required {
	public $type;
	public function render( $a ) {} // child requiring 2 is incompatible
	public function init() {}
}
class PKWYC_Guard_More_Params {
	public $type;
	public function render( $a, $b, $c = null, $d = null ) {} // child accepting 3 is incompatible
	public function init() {}
}
class PKWYC_Guard_Return_Type {
	public $type;
	public function render( $a, $b, $c = null ): string { return ''; }
	public function init() {}
}
class PKWYC_Guard_Final {
	public $type;
	final public function render( $a, $b, $c = null ) {}
	public function init() {}
}
class PKWYC_Guard_Static {
	public $type;
	public static function render( $a, $b, $c = null ) {}
	public function init() {}
}
class PKWYC_Guard_By_Ref {
	public $type;
	public function render( &$a, $b, $c = null ) {}
	public function init() {}
}
abstract class PKWYC_Guard_New_Abstract {
	public $type;
	public function render( $a, $b, $c = null ) {}
	public function init() {}
	abstract public function brand_new();
}
class PKWYC_Guard_Typed_Prop {
	public string $type = '';
	public function render( $a, $b, $c = null ) {}
	public function init() {}
}
class PKWYC_Guard_Static_Prop {
	public static $type;
	public function render( $a, $b, $c = null ) {}
	public function init() {}
}
final class PKWYC_Guard_Final_Class {
	public $type;
	public function render( $a, $b, $c = null ) {}
	public function init() {}
}
class PKWYC_Guard_Protected {
	public $type;
	protected function render( $a, $b, $c = null ) {} // child widening to public is fine
	public function init() {}
}
class PKWYC_Guard_Missing {
	public $type;
	public function init() {} // render() gone from the parent: the child simply adds it
}

pkwyc_assert( 'matching parent: no problems', pkwyc_signature_problems( 'PKWYC_Guard_Good', $spec ), array() );
pkwyc_assert( 'abstract methods the adapter implements: no problems', pkwyc_signature_problems( 'PKWYC_Guard_Abstract_Ok', $spec ), array() );
pkwyc_assert( 'protected parent method: no problems', pkwyc_signature_problems( 'PKWYC_Guard_Protected', $spec ), array() );
pkwyc_assert( 'method gone from parent: no problems', pkwyc_signature_problems( 'PKWYC_Guard_Missing', $spec ), array() );

foreach ( array(
	'PKWYC_Guard_Fewer_Required' => 'parent requires fewer parameters',
	'PKWYC_Guard_More_Params'    => 'parent takes more parameters',
	'PKWYC_Guard_Return_Type'    => 'parent declares a return type',
	'PKWYC_Guard_Final'          => 'parent method is final',
	'PKWYC_Guard_Static'         => 'parent method is static',
	'PKWYC_Guard_By_Ref'         => 'parent takes a parameter by reference',
	'PKWYC_Guard_New_Abstract'   => 'parent has a new abstract method',
	'PKWYC_Guard_Typed_Prop'     => 'parent property has a type',
	'PKWYC_Guard_Static_Prop'    => 'parent property is static',
	'PKWYC_Guard_Final_Class'    => 'parent class is final',
) as $class => $label ) {
	$p = pkwyc_signature_problems( $class, $spec );
	pkwyc_assert( "{$label}: reported", count( $p ) >= 1 && is_string( $p[0] ) && $p[0] !== '', true );
}
pkwyc_assert( 'missing parent class: reported', count( pkwyc_signature_problems( 'PKWYC_Guard_Nope', $spec ) ), 1 );

// The guard's verdict has to match what PHP actually does. Declare a child of
// each parent in a separate process: problems reported if and only if PHP fatals.
$src   = file_get_contents( __FILE__ );
$start = strpos( $src, 'class PKWYC_Guard_Good' );
$end   = strpos( $src, "pkwyc_assert( 'matching parent" );
$defs  = substr( $src, $start, $end - $start );
foreach ( array( 'PKWYC_Guard_Good', 'PKWYC_Guard_Abstract_Ok', 'PKWYC_Guard_Protected', 'PKWYC_Guard_Missing', 'PKWYC_Guard_Fewer_Required', 'PKWYC_Guard_More_Params', 'PKWYC_Guard_Return_Type', 'PKWYC_Guard_Final', 'PKWYC_Guard_Static', 'PKWYC_Guard_By_Ref', 'PKWYC_Guard_New_Abstract', 'PKWYC_Guard_Typed_Prop', 'PKWYC_Guard_Static_Prop', 'PKWYC_Guard_Final_Class' ) as $class ) {
	$child = "<?php\n" . $defs . "\nclass Child_{$class} extends {$class} { public \$type; public function render( \$a, \$b, \$c = null ) {} public function init() {} }\necho 'declared';\n";
	$file  = sys_get_temp_dir() . "/pkwyc-guard-{$class}-" . getmypid() . '.php';
	file_put_contents( $file, $child );
	$out = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file ) . ' 2>&1' );
	unlink( $file );
	$php_ok   = substr( trim( $out ), -8 ) === 'declared';
	$guard_ok = pkwyc_signature_problems( $class, $spec ) === array();
	pkwyc_assert( "guard agrees with PHP for {$class}", $guard_ok, $php_ok );
}

// A spec can be read back from a declared class, so the adapters' hand-kept
// specs can be checked against the classes they describe.
class PKWYC_Guard_Child_Sample extends PKWYC_Guard_Good {
	public $type;
	public function render( $a, $b, $c = null ) {}
	public function init() {}
}
pkwyc_assert( 'spec read back from a class', pkwyc_signature_spec_of( 'PKWYC_Guard_Child_Sample' ), $spec );

pkwyc_results();
