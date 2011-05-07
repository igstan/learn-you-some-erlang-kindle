all:
	php main.php && kindlegen -verbose -o learn-you-some-erlang.mobi build/book.opf
clean:
	rm -rf build
