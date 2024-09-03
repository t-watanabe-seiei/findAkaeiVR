{ pkgs }: {
	deps = [
   pkgs.sqlite
		pkgs.php82
		pkgs.php82Packages.composer
	];
}