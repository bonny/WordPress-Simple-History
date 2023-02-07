<?php

/**
 * Un-namespaced class so old loggers that exend \SimpleLogger
 * does not crash directly.
 *
 * Should this inherit \Loggers\SimpleLogger and that class should contain
 * a __call() with some.
 *
 * Add a notice that the usage id deprecated.
 */
class SimpleLogger {
	// TODO: add magic get, set, no make class extension crash.
}
