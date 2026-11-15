<?php
/**
 * Check that a field class can be declared before declaring it.
 *
 * Each adapter extends a class owned by the form plugin (WPForms_Field,
 * GF_Field). If the form plugin changes a method the adapter overrides, PHP
 * refuses to declare the adapter's class with a compile-time fatal that no
 * try/catch stops, and the whole site goes down on every request. The form
 * plugin can update itself at any time, between this plugin's releases.
 *
 * So before declaring, the adapter's methods and properties are compared with
 * the real parent through Reflection, using the rules PHP applies. Any mismatch
 * means the adapter is skipped, fail closed (see pkwyc_signature_guard_fail()),
 * and the site stays up.
 *
 * A spec is array( 'methods' => array( name => array( parameters, required ) ),
 * 'props' => array( name, ... ) ). The adapters keep theirs by hand;
 * tests/test-gf-signatures.php and tests/test-wpforms-render.php check each
 * against the class it describes with pkwyc_signature_spec_of().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Why a class following $spec could not be declared as a child of $parent.
 *
 * @param string $parent Parent class name.
 * @param array  $spec   What the child declares.
 * @return string[] One line per problem; empty when the child fits.
 */
function pkwyc_signature_problems( $parent, $spec ) {

	if ( ! class_exists( $parent, false ) ) {
		return array( $parent . ' is not loaded' );
	}

	$class    = new ReflectionClass( $parent );
	$methods  = isset( $spec['methods'] ) ? (array) $spec['methods'] : array();
	$props    = isset( $spec['props'] ) ? (array) $spec['props'] : array();
	$problems = array();

	if ( $class->isFinal() ) {
		$problems[] = $parent . ' is final';
	}

	foreach ( $class->getMethods( ReflectionMethod::IS_ABSTRACT ) as $abstract ) {
		if ( ! isset( $methods[ $abstract->getName() ] ) ) {
			$problems[] = $parent . '::' . $abstract->getName() . '() is abstract and not implemented';
		}
	}

	foreach ( $methods as $name => $shape ) {

		if ( ! $class->hasMethod( $name ) ) {
			continue;
		}

		$m = $class->getMethod( $name );

		if ( $m->isPrivate() ) {
			continue;
		}

		$label = $parent . '::' . $name . '()';

		if ( $m->isFinal() ) {
			$problems[] = $label . ' is final';
		}
		if ( $m->isStatic() ) {
			$problems[] = $label . ' is static';
		}
		if ( $m->hasReturnType() ) {
			$problems[] = $label . ' declares a return type';
		}
		if ( (int) $shape[0] < $m->getNumberOfParameters() ) {
			$problems[] = $label . ' takes ' . $m->getNumberOfParameters() . ' parameters, the adapter ' . (int) $shape[0];
		}
		if ( (int) $shape[1] > $m->getNumberOfRequiredParameters() ) {
			$problems[] = $label . ' requires ' . $m->getNumberOfRequiredParameters() . ' parameters, the adapter ' . (int) $shape[1];
		}
		foreach ( $m->getParameters() as $param ) {
			if ( $param->isPassedByReference() || $param->isVariadic() ) {
				$problems[] = $label . ' takes $' . $param->getName() . ' by reference or as variadic';
			}
		}
	}

	foreach ( $props as $name ) {

		if ( ! $class->hasProperty( $name ) ) {
			continue;
		}

		$p = $class->getProperty( $name );

		if ( $p->isPrivate() ) {
			continue;
		}
		if ( $p->isStatic() ) {
			$problems[] = $parent . '::$' . $name . ' is static';
		}
		if ( method_exists( $p, 'hasType' ) && $p->hasType() ) {
			$problems[] = $parent . '::$' . $name . ' has a type';
		}
	}

	return $problems;
}

/**
 * The spec of a declared class: its own public methods and properties.
 *
 * @param string $class_name A declared class.
 * @return array
 */
function pkwyc_signature_spec_of( $class_name ) {

	$class   = new ReflectionClass( $class_name );
	$methods = array();
	$props   = array();

	foreach ( $class->getMethods() as $m ) {
		if ( $m->getDeclaringClass()->getName() === $class->getName() ) {
			$methods[ $m->getName() ] = array( $m->getNumberOfParameters(), $m->getNumberOfRequiredParameters() );
		}
	}
	foreach ( $class->getProperties() as $p ) {
		if ( $p->getDeclaringClass()->getName() === $class->getName() && ! $p->isStatic() ) {
			$props[] = $p->getName();
		}
	}

	return array( 'methods' => $methods, 'props' => $props );
}

/**
 * The adapter for $platform was not declared: record why, log it once per
 * request, and keep the site safe without it.
 *
 * Without the field class the form plugin no longer knows the Consent type.
 * That must fail closed (audit 2026-11-14, M3): the emergency switch's hooks
 * are reused, so WPForms records the field blank and compares it as text (no
 * Mailchimp rule on Consent passes) and Gravity Forms stops requiring a box it
 * can no longer show.
 *
 * @param string   $platform 'WPForms' or 'Gravity Forms'.
 * @param string[] $problems From pkwyc_signature_problems().
 */
function pkwyc_signature_guard_fail( $platform, $problems ) {

	$GLOBALS['pkwyc_signature_failures'][ $platform ] = $problems;

	if ( function_exists( 'error_log' ) ) {
		error_log( 'PKWY Compliance: Consent field turned off on ' . $platform . ', its field class no longer fits: ' . implode( '; ', $problems ) );
	}

	if ( ! function_exists( 'add_filter' ) ) {
		return;
	}

	if ( $platform === 'WPForms' ) {
		foreach ( pkwyc_kill_switch_wpforms_hooks() as $hook ) {
			add_filter( $hook[0], $hook[1], $hook[2], $hook[3] );
		}
	} else {
		foreach ( pkwyc_kill_switch_gf_hooks() as $hook ) {
			add_filter( $hook, 'pkwyc_kill_switch_relax_gf_form' );
		}
	}

	if ( ! has_action( 'admin_notices', 'pkwyc_signature_guard_notice' ) ) {
		add_action( 'admin_notices', 'pkwyc_signature_guard_notice' );
	}
}

/**
 * Say so in wp-admin, so a turned-off field is not mistaken for a working one.
 */
function pkwyc_signature_guard_notice() {

	if ( empty( $GLOBALS['pkwyc_signature_failures'] ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	foreach ( $GLOBALS['pkwyc_signature_failures'] as $platform => $problems ) {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p><p><code>%s</code></p></div>',
			esc_html__( 'PKWY Compliance has turned off its Consent field.', 'pkwycompliance' ),
			esc_html(
				sprintf(
					/* translators: %s: WPForms or Gravity Forms. */
					__( 'An update to %s changed code the field is built on, and loading the field would have crashed the site. Forms still submit, without the consent box, and no Mailchimp connection that requires Consent subscribes anyone. Update PKWY Compliance; if no newer version is offered yet, Parkway Digital needs to release one.', 'pkwycompliance' ),
					$platform
				)
			),
			esc_html( implode( '; ', $problems ) )
		);
	}
}
