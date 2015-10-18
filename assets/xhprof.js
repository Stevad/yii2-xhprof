var XHProf = {
    urlReportTemplate: undefined,
    urlCallgraphTemplate: undefined,
    urlDiffTemplate: undefined,
    compare: {
        run1: undefined,
        run2: undefined,
        ns: undefined
    },
    getReportUrl: function(id, ns) {
       return this.urlReportTemplate.replace('%%ID%%', id).replace('%%NAMESPACE%%', ns);
    },
    getCallgraphUrl: function(id, ns) {
        return this.urlCallgraphTemplate.replace('%%ID%%', id).replace('%%NAMESPACE%%', ns);
    },
    getDiffUrl: function(id1, id2, ns) {
        return this.urlDiffTemplate.replace('%%ID1%%', id1).replace('%%ID2%%', id2).replace('%%NAMESPACE%%', ns);
    }
};

$(document).ready(function() {
    $('.xhprof-compare').click(function() {
        if ($(this).hasClass('disabled')) {
            return;
        }

        window.open(XHProf.getDiffUrl(XHProf.compare.run1, XHProf.compare.run2, XHProf.compare.ns));
    });

    $('.xhprof-report').each(function(idx, link) {
        var data = $(link).data();
        $(link).attr('href', XHProf.getReportUrl(data.id, data.ns));
    });

    $('.xhprof-callgraph').each(function(idx, link) {
        var data = $(link).data();
        $(link).attr('href', XHProf.getCallgraphUrl(data.id, data.ns));
    });

    $('.xhprof-diff').each(function(idx, link) {
        var data = $(link).data();
        $(link).attr('href', XHProf.getDiffUrl(data.id2, data.id, data.ns));
    });

    $('input[type="radio"]').click(function() {
        var data = $(this).data();
        XHProf.compare['run' + data.type] = $(this).val();

        if (!XHProf.compare.ns) {
            XHProf.compare.ns = data.ns;
        } else if (XHProf.compare.ns !== data.ns && XHProf.compare.run1 && XHProf.compare.run2) {
            alert('For some reason this two runs has different namespaces. They cannot be compared');
            return false;
        }

        if (XHProf.compare.run1 === XHProf.compare.run2) {
            alert('You must compare two different runs');
            XHProf.compare['run' + data.type] = undefined;
            return false;
        }

        if (XHProf.compare.run1 && XHProf.compare.run2) {
            $('.xhprof-compare').removeClass('disabled');
        }
    });
});