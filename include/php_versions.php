<?php

	/*
	The RC and dev versions are pulled from the http://qa.php.net/api.php
	if you want to add a new version, add it there at include/release-qa.php
	the result is cached for an hour, you can force it to refresh, if you delete the 'bugs.versions' item from apc
	the versions are weighted by the following:
	- major+minor version desc (7>5.4>5.3>master)
	- between a minor version we order by the micro if available: first the qa releases: alpha/beta/rc, then the stable, then the Git versions(snaps, Git)

	Stable releases are pulled from http://php.net/releases/active.php
	*/

	// Custom versions appended to the list
	$custom_versions = array(
		'Next Major Version',
		'Next Minor Version',
		'Irrelevant'
	);

	if (!$versions = apc_fetch('bugs.versions')) {
		$versions = buildVersions();
		if ($versions) {
 			apc_store('bugs.versions', $versions, 3600);
		}
	}

	$versions = array_merge($versions, $custom_versions);

	function buildVersions() {
		$dev_versions = json_decode(file_get_contents('http://qa.php.net/api.php?type=qa-releases&format=json&only=dev_versions'));

		$versions = array();

		$date = date('Y-m-d');
		$default_versions = array(
			"Git-{$date} (snap)",
			"Git-{$date} (Git)",
		);

		foreach ($dev_versions as $dev_version) {
			$dev_version_parts = parseVersion($dev_version);

			// if it is a dev version, then add that branch, add the minor-1 version, if it's appropriate
			if ($dev_version_parts['type'] == 'dev') {
				if (!isset($versions[$dev_version_parts['major']][$dev_version_parts['minor']])) {
					$versions[$dev_version_parts['major']][$dev_version_parts['minor']] = array();
				}
			}
			// then it is a qa version (alpha|beta|rc)
			else {
				$versions[$dev_version_parts['major']][$dev_version_parts['minor']][$dev_version_parts['micro']] = $dev_version_parts;
				ksort($versions[$dev_version_parts['major']][$dev_version_parts['minor']]);
			}
		}

		$stable_releases = json_decode(file_get_contents('http://us3.php.net/releases/active.php'), true);
		foreach ($stable_releases as $major => $major_releases) {
			foreach ($major_releases as $release) {
				$version_parts = parseVersion($release['version']);
				$versions[$version_parts['major']][$version_parts['minor']][$version_parts['micro']] = $version_parts;
				ksort($versions[$version_parts['major']][$version_parts['minor']]);
			}
		}

		$flat_versions = array();

		// add master to the end of the list
		foreach ($default_versions as $default_version) {
			$flat_versions[] = 'master-'.$default_version;
		}

		// add the fetched versions to the list
		foreach ($versions as $major_number => $major) {
			foreach ($major as $minor_number => $minor) {
				// add the default versions to ever minor branch
				foreach ($default_versions as $default_version) {
					$flat_versions[] = $major_number.'.'.$minor_number.$default_version;
				}
				foreach ($minor as $micro_number => $micro) {
					$flat_versions[] = $micro['original_version'];
				}
			}
		}

		// reverse the order, this makes it descending
		$flat_versions = array_reverse($flat_versions);

		return $flat_versions;
	}


	function parseVersion($version){
		$version_parts	= array();
		$raw_parts	= array();
		preg_match('#(?P<major>\d+)\.(?P<minor>\d+).(?P<micro>\d+)[-]?(?P<type>RC|alpha|beta|dev)?(?P<number>[\d]?).*#ui', $version, $raw_parts);
		$version_parts = array(
			'major'			=> $raw_parts['major'],
			'minor'			=> $raw_parts['minor'],
			'micro'			=> $raw_parts['micro'],
			'type'			=> strtolower($raw_parts['type']?$raw_parts['type']:'stable'),
			'number'		=> $raw_parts['number'],
			'original_version'	=> $version,
		);
		return $version_parts;
	}
