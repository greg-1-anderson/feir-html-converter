## FEIR HTML Conversion

These scripts were used to convert the [Brisbane Baylands Final Environmental Impact Report](http://brisbaneca.org/feir-documents).  The results of the conversion are available on the [Baylands Searchable FEIR Documents](http://brisbaneca.org/baylands-searchable-feir-documents) on the Brisbane City website.

The initial conversion of these documents from PDF to HTML was done with the [VeryPDF PDF to HTML](http://www.verypdf.com/app/pdf-to-html-converter/try-and-buy.html#buy) product.  Script development was done with the free trial version, which will convert the first three pages of any document to HTML (including a "trial version" watermark).  The final documents were converted with a licensed version, purchased once the scripts were working.

## Usage
```
$ feir_fix_all SOURCE_FOLDER
```
This will produce output in folders named "*-FIXED" and "*-SINGLE".  The "FIXED" version contains the converted document with each PDF page stored in a separate HTML page.  The "SINGLE" version produces a very large single HTML page that contains all of the pages from all of the original source documents.

## Features

These scripts add a navigation header to the top of each page, and fix up the cross-links between different parts of the documents.

## Disclaimers

These scripts were written as a one-off; the code is rough and lightly documented.  They are probably not useful except in conjunction with the VeryPDF product they were designed to be used with. Results of the hyperlink fixup logic may be unsatisfactory if used with other source documents.

Read and use at your own risk.

## License

The scripts in this repository are in the public domain, and may be used for any purpose, without warantee or guarantee.  The VeryPDF product may be used under the terms set out by the provider.
