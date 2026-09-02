"use client";

import { useEffect, useState } from "react";

import { request } from "@/lib/api/request";

export interface DepartmentOption {
  id: number;
  name: string;
}

/**
 * The department list, for filters and pickers.
 *
 * Empty until it loads rather than guessed: a picker showing a stale or
 * invented department would let someone file a customer under one that does not
 * exist, and the server would refuse on submit with nothing useful to say.
 */
export function useDepartments(): DepartmentOption[] {
  const [departments, setDepartments] = useState<DepartmentOption[]>([]);

  useEffect(() => {
    let cancelled = false;

    request<{ data: DepartmentOption[] }>("/departments", { method: "GET" })
      .then((body) => {
        if (!cancelled) setDepartments(body.data);
      })
      .catch(() => undefined);

    return () => {
      cancelled = true;
    };
  }, []);

  return departments;
}
