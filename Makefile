
version = $(error version is not set)

.DEFAULT_GOAL:= debian-package
.PHONY: clean

clean:
	sudo rm -Rf package

debian-package-dependencies:
	sudo apt install build-essential fakeroot devscripts dupload

debian-package-version:
	dch -v $(version)

debian-package:
	debuild -us -uc -Zxz
	mkdir package || true
	mv ../svnmentions_* package/

debsign:
	cd package && debsign svnmentions_$(version)_amd64.changes

dupload:
	cd package && dupload --to debian-mentors
