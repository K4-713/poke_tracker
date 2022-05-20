/* 
 * Copyright (C) 2020 K4-713 <k4@hownottospellwinnebago.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

var width = 200;
var height = 50;
var x = d3.scaleLinear().range([0, width - 2]);
var y = d3.scaleLinear().range([height - 4, 0]);
var parseDate = d3.timeFormat("%B %d, %Y");
var line = d3.line()
	.x(function (d) {
	    return x(d.date);
	})
	.y(function (d) {
	    return y(d.close);
	})
	.curve(d3.curveBasis);

function sparkline(elemId, data) {
    data.forEach(function (d) {
	d.date = d.ts;
	d.close = d.ssw;
	console.log(d.date + " : " + d.close);
	if (d.close === undefined) {
	    d.close = d.tp;
	}
	if (d.close === undefined) {
	    d.close = d.jn;
	}
    });
    x.domain(d3.extent(data, function (d) {
	return d.date;
    }));
    y.domain(d3.extent(data, function (d) {
	return d.close;
    }));

    var svg = d3.select(elemId)
	    .append('svg')
	    .attr('width', width)
	    .attr('height', height)
	    .append('g')
	    .attr('transform', 'translate(0, 2)');
    svg.append('path')
	    .datum(data)
	    .attr('class', 'sparkline')
	    .attr('d', line);
    svg.append('circle')
	    .attr('class', 'sparkcircle')
	    .attr('cx', x(data[data.length - 1].date))
	    .attr('cy', y(data[data.length - 1].close))
	    .attr('r', 1.5);
}

sparkline('#sparkline', item_data);