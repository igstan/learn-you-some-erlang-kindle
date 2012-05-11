all:
	php -d short_open_tag=off -d display_errors=1 main.php && \
	kindlegen -verbose -o learn-you-some-erlang.mobi build/book.opf
clean:
	rm -rf build
