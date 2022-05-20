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

class Line_Graph {

    constructor(name = 'graph', container = 'body', width = 800, height = 600) {
	this.name = name;
	this.width = width;
	this.height = height;
	this.v_label_width = 60;
	this.h_label_height = 20;

	this.recalculate_axis_dimensions();
	this.svg = d3.select(container).append("svg")
		.attr("width", width)
		.attr("height", height)
		.attr("class", "graph")
		.attr("id", name);
    }

    bind_data(data, graph_these_keys) {
	this.data = [];
	for (var key in data) {
	    var temp = {};
	    temp.x = this.recast_value(key);
	    graph_these_keys.forEach(function (item) {
		temp[item] = data[key][item];
	    });
	    this.data.push(temp);
	}
	console.log(this.data);
	//vertical domain = zero to max of all columns we want to graph
	//horizontal domain = min to max of all... keys.

	//this is fine if we always want to start with zero, which.. .probably.
	this.v_domain = [0, d3.max(this.data, function (d) {
	    return Line_Graph.get_max_from_keys(d, graph_these_keys);
	    })];
	this.v_scale = d3.scaleLinear()
		.domain(this.v_domain)
		.range([this.h_height, 0]);

	this.h_domain = d3.extent(this.data, function (d) {
	    return d.x;
	});
	this.h_scale = d3.scaleTime()
		.domain(this.h_domain)
		.range([0, this.v_width]);

	this.add_vertical_axis();
	this.add_horizontal_axis();

	var self = this;
	graph_these_keys.forEach(function (item) {
	    self.add_graph_line(item);
	});

    }

    recalculate_axis_dimensions() {
	this.v_width = this.width - this.v_label_width - 10;
	this.h_height = this.height - this.h_label_height - 5;
	this.h_translate_down = this.height - this.h_label_height;
	this.v_translate_down = this.h_label_height - 15;

	//make adding graph stuff a tiny bit easier...
	this.origin_x = this.v_label_width;
	this.origin_y = this.h_height + 5;
    }

    add_graph_line(key) {
	//console.log("Trying to draw a line for " + key);
	var line_class = key + '_line';
	var point_class = key + '_point';
	var self = this;
	var h_scale_function = this.h_scale;
	var v_scale_function = this.v_scale;
	var my_line = d3.line()
		.x(d => h_scale_function(d.x) + self.v_label_width)
		.y(d => v_scale_function(d[key]) + 5)
		.curve(d3.curveMonotoneX);
	var my_point = d3.symbol().type(d3.symbolStar).size(10);
	this.svg.append("g")
		.attr('class', key)
		.append("path")
		.datum(this.data)
		.attr('class', line_class)
		.attr("d", my_line)
		.style('fill', 'none');
	this.svg.selectAll('g.' + line_class)
		.data(this.data).join("path")
		.attr("class", point_class)
		.attr('d', my_point)
		.attr("transform", function (k) {
		    var trans = "translate(" + (h_scale_function(k.x) + self.v_label_width) + ","
			    + (v_scale_function(k[key]) + 5) + ")";
		    return trans;
		});
    }

    add_vertical_axis(data = null) {
	this.v_axis = d3.axisLeft(this.v_scale);
	this.v_axis(this.svg.append("g")
		.attr("transform", "translate(" + this.v_label_width + ", " + this.v_translate_down + ")")); //have to move it from origin
    }

    add_horizontal_axis(data = null) {
	this.h_axis = d3.axisBottom(this.h_scale);
	this.h_axis(this.svg.append("g")
		.attr("transform", "translate(" + this.v_label_width + ", " + this.h_translate_down + ")")); //have to move it from origin
    }

    test_origin() {
	//now, make sure you know where the graph origin is...
	this.svg.append("circle")
		.attr('r', 5)
		.attr('cx', this.origin_x)
		.attr('cy', this.origin_y)
		.style('fill', '#ff0000');
    }

    recast_value(val) {
	if (val.match(/\d{4}\-{1}\d{2}\-{1}\d{2}$/)) {
	    return new Date(val);
	}
	return val;
    }

    static get_max_from_keys(obj, keys) {
	var max = 0;
	keys.forEach(function (item) {
	    if (parseInt(obj[item]) > max) {
		max = parseInt(obj[item]);
	    }
	});
	return max;
    }
}